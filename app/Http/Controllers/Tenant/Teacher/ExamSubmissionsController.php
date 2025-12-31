<?php

namespace App\Http\Controllers\Tenant\Teacher;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ExamSubmissionsController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = TenantContext::id();
        $teacherId = $request->user()->id;

        $currentSession = TenantDB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? TenantDB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        if (! $currentSession || ! $currentTerm) {
            return response()->json(['message' => 'Current academic session/term is not set.'], 400);
        }

        $items = TenantDB::table('exam_question_submissions as s')
            ->join('classes as c', function ($j) {
                $j->on('c.id', '=', 's.class_id')
                    ->on('c.tenant_id', '=', 's.tenant_id');
            })
            ->join('subjects as sub', function ($j) {
                $j->on('sub.id', '=', 's.subject_id')
                    ->on('sub.tenant_id', '=', 's.tenant_id');
            })
            ->where('s.teacher_id', $teacherId)
            ->where('s.academic_session_id', $currentSession->id)
            ->where('s.term_id', $currentTerm->id)
            ->orderByDesc('s.id')
            ->get([
                's.id',
                's.exam_type',
                's.duration_minutes',
                's.question_count',
                's.status',
                's.rejection_reason',
                's.created_at',
                'c.name as class_name',
                'sub.name as subject_name',
                'sub.code as subject_code',
            ]);

        return response()->json([
            'meta' => [
                'academic_session_id' => $currentSession->id,
                'term_id' => $currentTerm->id,
            ],
            'data' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();
        $teacherId = $request->user()->id;

        $data = $request->validate([
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')->where('tenant_id', $tenantId)],
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')->where('tenant_id', $tenantId)],
            'exam_type' => ['required', 'in:objective,theory,fill_blank'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:600'],
            'question_count' => ['nullable', 'integer', 'min:1', 'max:300'],
            'marks_per_question' => ['nullable', 'integer', 'min:1', 'max:100'],
            'paper_pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            // For MVP: objective parsing only supports txt. doc/docx allowed only for non-objective (admin download).
            'source_file' => ['nullable', 'file', 'mimes:txt,doc,docx', 'max:10240'],
        ]);

        $currentSession = TenantDB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? TenantDB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        if (! $currentSession || ! $currentTerm) {
            return response()->json(['message' => 'Current academic session/term is not set.'], 400);
        }

        // Teacher must be assigned to class and subject.
        $okClass = TenantDB::table('teacher_class')->where('teacher_id', $teacherId)->where('class_id', (int) $data['class_id'])->exists();
        $okSub = TenantDB::table('teacher_subject')->where('teacher_id', $teacherId)->where('subject_id', (int) $data['subject_id'])->exists();
        if (! $okClass || ! $okSub) {
            return response()->json(['message' => 'You are not assigned to this class/subject.'], 403);
        }

        if (in_array($data['exam_type'], ['theory', 'fill_blank'], true) && empty($data['question_count'])) {
            return response()->json(['message' => 'question_count is required for theory/fill_blank exams.'], 422);
        }

        if ($data['exam_type'] === 'objective') {
            $src = $request->file('source_file');
            if (! $src) {
                return response()->json(['message' => 'For objective exams, please upload a source file (TXT recommended) so the system can build the options UI.'], 422);
            }
            $ext = strtolower((string) $src->getClientOriginalExtension());
            if ($ext !== 'txt') {
                return response()->json(['message' => 'For objective exams, source_file must be a .txt so the system can parse questions/options.'], 422);
            }
        }

        // Partition storage by tenant_id to prevent cross-tenant collisions/leakage on shared hosting.
        $base = "tenants/{$tenantId}/exams/submissions/{$currentSession->id}/{$currentTerm->id}/{$data['class_id']}/{$data['subject_id']}/{$teacherId}";

        $paperPdfPath = $request->file('paper_pdf')->store($base, 'public');

        $sourcePath = null;
        $sourceOriginal = null;
        if ($request->file('source_file')) {
            $sourceOriginal = $request->file('source_file')->getClientOriginalName();
            $sourcePath = $request->file('source_file')->store($base, 'public');
        }

        $id = DB::table('exam_question_submissions')->insertGetId([
            'tenant_id' => $tenantId,
            'teacher_id' => $teacherId,
            'class_id' => (int) $data['class_id'],
            'subject_id' => (int) $data['subject_id'],
            'academic_session_id' => $currentSession->id,
            'term_id' => $currentTerm->id,
            'exam_type' => $data['exam_type'],
            'duration_minutes' => (int) $data['duration_minutes'],
            'question_count' => isset($data['question_count']) ? (int) $data['question_count'] : null,
            'marks_per_question' => isset($data['marks_per_question']) ? (int) $data['marks_per_question'] : null,
            'paper_pdf_path' => $paperPdfPath,
            'source_file_path' => $sourcePath,
            'source_file_original_name' => $sourceOriginal,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('teacher_activities')->insert([
            'tenant_id' => $tenantId,
            'teacher_id' => $teacherId,
            'action' => 'exam_question_submitted',
            'metadata' => json_encode([
                'submission_id' => $id,
                'class_id' => (int) $data['class_id'],
                'subject_id' => (int) $data['subject_id'],
                'exam_type' => $data['exam_type'],
            ]),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 5000),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function downloadPaper(Request $request, int $id)
    {
        $teacherId = $request->user()->id;
        $row = TenantDB::table('exam_question_submissions')->where('id', $id)->where('teacher_id', $teacherId)->first();
        if (! $row) return response()->json(['message' => 'Submission not found.'], 404);

        return Storage::disk('public')->download($row->paper_pdf_path, "exam-paper-{$id}.pdf");
    }

    public function downloadSource(Request $request, int $id)
    {
        $teacherId = $request->user()->id;
        $row = TenantDB::table('exam_question_submissions')->where('id', $id)->where('teacher_id', $teacherId)->first();
        if (! $row) return response()->json(['message' => 'Submission not found.'], 404);
        if (! $row->source_file_path) return response()->json(['message' => 'No source file for this submission.'], 404);

        $name = $row->source_file_original_name ?: "exam-source-{$id}";
        return Storage::disk('public')->download($row->source_file_path, $name);
    }
}


