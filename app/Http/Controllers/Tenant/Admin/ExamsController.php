<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ExamsController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = TenantContext::id();

        $currentSession = TenantDB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? TenantDB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        if (! $currentSession || ! $currentTerm) {
            return response()->json(['message' => 'Current academic session/term is not set.'], 400);
        }

        $data = $request->validate([
            'class_id' => ['nullable', 'integer', Rule::exists('classes', 'id')->where('tenant_id', $tenantId)],
            'subject_id' => ['nullable', 'integer', Rule::exists('subjects', 'id')->where('tenant_id', $tenantId)],
            'status' => ['nullable', 'in:approved,live,ended'],
        ]);

        $q = DB::table('exams as e')
            ->where('e.tenant_id', $tenantId)
            ->join('classes as c', function ($j) {
                $j->on('c.id', '=', 'e.class_id')
                    ->on('c.tenant_id', '=', 'e.tenant_id');
            })
            ->join('subjects as sub', function ($j) {
                $j->on('sub.id', '=', 'e.subject_id')
                    ->on('sub.tenant_id', '=', 'e.tenant_id');
            })
            ->join('exam_question_submissions as s', function ($j) {
                $j->on('s.id', '=', 'e.submission_id')
                    ->on('s.tenant_id', '=', 'e.tenant_id');
            })
            ->join('users as t', function ($j) {
                $j->on('t.id', '=', 's.teacher_id')
                    ->on('t.tenant_id', '=', 'e.tenant_id');
            })
            ->where('e.academic_session_id', $currentSession->id)
            ->where('e.term_id', $currentTerm->id);

        if (! empty($data['class_id'])) $q->where('e.class_id', (int) $data['class_id']);
        if (! empty($data['subject_id'])) $q->where('e.subject_id', (int) $data['subject_id']);
        if (! empty($data['status'])) $q->where('e.status', $data['status']);

        $items = $q->orderByDesc('e.id')->limit(500)->get([
            'e.id',
            'e.code',
            'e.exam_type',
            'e.duration_minutes',
            'e.question_count',
            'e.status',
            'e.started_at',
            'e.ended_at',
            'c.name as class_name',
            'sub.name as subject_name',
            'sub.code as subject_code',
            't.name as teacher_name',
            't.username as teacher_username',
        ]);

        return response()->json([
            'meta' => [
                'academic_session_id' => $currentSession->id,
                'term_id' => $currentTerm->id,
            ],
            'data' => $items,
        ]);
    }

    public function start(Request $request, int $id)
    {
        $row = TenantDB::table('exams')->where('id', $id)->first();
        if (! $row) return response()->json(['message' => 'Exam not found.'], 404);
        if ($row->status === 'ended') return response()->json(['message' => 'Exam already ended.'], 409);

        TenantDB::table('exams')->where('id', $id)->update([
            'status' => 'live',
            'started_at' => $row->started_at ?: now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Exam started.']);
    }

    public function end(Request $request, int $id)
    {
        $exam = TenantDB::table('exams')->where('id', $id)->first();
        if (! $exam) return response()->json(['message' => 'Exam not found.'], 404);
        if ($exam->status === 'ended') return response()->json(['message' => 'Exam already ended.'], 409);

        DB::transaction(function () use ($id) {
            TenantDB::table('exams')->where('id', $id)->update([
                'status' => 'ended',
                'ended_at' => now(),
                'updated_at' => now(),
            ]);

            // Force-submit all in-progress attempts (no grading here; teacher can grade/auto-grade).
            TenantDB::table('exam_attempts')
                ->where('exam_id', $id)
                ->where('status', 'in_progress')
                ->update([
                    'status' => 'submitted',
                    'submitted_at' => now(),
                    'updated_at' => now(),
                ]);
        });

        return response()->json(['message' => 'Exam ended and all active attempts were submitted.']);
    }

    public function monitor(Request $request, int $id)
    {
        $tenantId = TenantContext::id();

        $exam = DB::table('exams as e')
            ->where('e.tenant_id', $tenantId)
            ->join('classes as c', function ($j) {
                $j->on('c.id', '=', 'e.class_id')
                    ->on('c.tenant_id', '=', 'e.tenant_id');
            })
            ->join('subjects as sub', function ($j) {
                $j->on('sub.id', '=', 'e.subject_id')
                    ->on('sub.tenant_id', '=', 'e.tenant_id');
            })
            ->where('e.id', $id)
            ->first([
                'e.id',
                'e.code',
                'e.exam_type',
                'e.duration_minutes',
                'e.status',
                'e.started_at',
                'e.ended_at',
                'e.class_id',
                'c.name as class_name',
                'sub.name as subject_name',
            ]);

        if (! $exam) return response()->json(['message' => 'Exam not found.'], 404);

        $students = DB::table('users as u')
            ->where('u.tenant_id', $tenantId)
            ->join('student_profiles as sp', function ($j) {
                $j->on('sp.user_id', '=', 'u.id')
                    ->on('sp.tenant_id', '=', 'u.tenant_id');
            })
            ->where('u.role', 'student')
            ->where('sp.current_class_id', $exam->class_id)
            ->select(['u.id', 'u.admission_number', 'sp.first_name', 'sp.last_name'])
            ->orderBy('sp.last_name')
            ->get()
            ->map(fn ($s) => [
                'student_id' => $s->id,
                'admission_number' => $s->admission_number,
                'name' => trim($s->last_name . ' ' . $s->first_name),
            ]);

        $attempts = TenantDB::table('exam_attempts')
            ->where('exam_id', $id)
            ->get()
            ->keyBy('student_id');

        $rows = $students->map(function ($s) use ($attempts) {
            $a = $attempts->get($s['student_id']);
            return [
                ...$s,
                'attempt_status' => $a->status ?? 'not_started',
                'started_at' => $a->started_at ?? null,
                'last_seen_at' => $a->last_seen_at ?? null,
                'submitted_at' => $a->submitted_at ?? null,
                'objective_score' => $a->objective_score ?? null,
                'total_score' => $a->total_score ?? null,
                'continue_key' => $a->continue_key_plain ?? null, // Show continue key to admin for monitoring
            ];
        });

        $stats = [
            'total_students' => $students->count(),
            'not_started' => $rows->where('attempt_status', 'not_started')->count(),
            'in_progress' => $rows->where('attempt_status', 'in_progress')->count(),
            'submitted' => $rows->where('attempt_status', 'submitted')->count(),
        ];

        return response()->json([
            'exam' => $exam,
            'stats' => $stats,
            'students' => $rows->values(),
        ]);
    }
}


