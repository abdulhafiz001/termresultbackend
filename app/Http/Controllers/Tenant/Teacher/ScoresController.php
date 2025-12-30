<?php

namespace App\Http\Controllers\Tenant\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Admin\GradingConfigsController;
use App\Support\TenantCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ScoresController extends Controller
{
    public function listForClassSubject(Request $request)
    {
        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
        ]);

        $currentSession = DB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? DB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        if (! $currentSession || ! $currentTerm) {
            return response()->json(['message' => 'Current academic session/term is not set.'], 400);
        }

        $school = app('tenant.school');
        $cacheKey = TenantCache::teacherScoresListKey(
            (int) $school->id,
            (int) $data['class_id'],
            (int) $data['subject_id'],
            (int) $currentSession->id,
            (int) $currentTerm->id
        );

        $payload = Cache::remember($cacheKey, now()->addSeconds(60), function () use ($data, $currentSession, $currentTerm) {
            $classId = (int) $data['class_id'];

            // Active grading config (for teacher UI previews).
            $gradingConfig = DB::table('grading_configs as gc')
                ->join('grading_config_classes as gcc', 'gcc.grading_config_id', '=', 'gc.id')
                ->where('gc.is_active', true)
                ->where('gcc.class_id', $classId)
                ->orderByDesc('gc.id')
                ->select(['gc.id', 'gc.name'])
                ->first();

            $gradingRanges = collect();
            if ($gradingConfig) {
                $gradingRanges = DB::table('grading_config_ranges')
                    ->where('grading_config_id', $gradingConfig->id)
                    ->orderByDesc('min_score')
                    ->get(['grade', 'min_score', 'max_score']);
            }

            $students = DB::table('users')
                ->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')
                ->where('users.role', 'student')
                ->where('student_profiles.current_class_id', $classId)
                ->select([
                    'users.id as student_id',
                    'users.admission_number',
                    'student_profiles.first_name',
                    'student_profiles.last_name',
                ])
                ->orderBy('student_profiles.last_name')
                ->get();

            $scores = DB::table('student_scores')
                ->where('academic_session_id', $currentSession->id)
                ->where('term_id', $currentTerm->id)
                ->where('subject_id', (int) $data['subject_id'])
                ->get()
                ->keyBy('student_id');

            $rows = $students->map(function ($s) use ($scores, $classId) {
                $sc = $scores->get($s->student_id);
                $totalInt = ($sc && is_numeric($sc->total)) ? (int) round((float) $sc->total) : null;
                $dynamicGrade = $classId ? GradingConfigsController::gradeForClassTotal($classId, $totalInt) : null;
                return [
                    'student_id' => $s->student_id,
                    'admission_number' => $s->admission_number,
                    'name' => trim($s->last_name.' '.$s->first_name),
                    'ca1' => $sc->ca1 ?? null,
                    'ca2' => $sc->ca2 ?? null,
                    'exam' => $sc->exam ?? null,
                    'total' => $sc->total ?? null,
                    'grade' => $dynamicGrade ?: ($sc->grade ?? null),
                    'remark' => $sc->remark ?? null,
                ];
            });

            return [
                'meta' => [
                    'academic_session_id' => $currentSession->id,
                    'term_id' => $currentTerm->id,
                    'grading_config' => $gradingConfig ? ['id' => $gradingConfig->id, 'name' => $gradingConfig->name] : null,
                    'grading_ranges' => $gradingRanges
                        ->map(fn ($r) => ['grade' => (string) $r->grade, 'min_score' => (int) $r->min_score, 'max_score' => (int) $r->max_score])
                        ->values(),
                ],
                'data' => $rows,
            ];
        });

        return response()->json($payload);
    }

    public function upsert(Request $request)
    {
        $data = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'student_id' => ['required', 'integer', 'exists:users,id'],
            'ca1' => ['nullable', 'integer', 'min:0', 'max:100'],
            'ca2' => ['nullable', 'integer', 'min:0', 'max:100'],
            'exam' => ['nullable', 'integer', 'min:0', 'max:100'],
            'remark' => ['nullable', 'string', 'max:255'],
        ]);

        $currentSession = DB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? DB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        if (! $currentSession || ! $currentTerm) {
            return response()->json(['message' => 'Current academic session/term is not set.'], 400);
        }

        $total = null;
        if ($data['ca1'] !== null || $data['ca2'] !== null || $data['exam'] !== null) {
            $total = (int) (($data['ca1'] ?? 0) + ($data['ca2'] ?? 0) + ($data['exam'] ?? 0));
        }

        $grade = $total === null ? null : $this->gradeFromTotal($total);

        $studentClassId = (int) (DB::table('student_profiles')->where('user_id', (int) $data['student_id'])->value('current_class_id') ?? 0);
        if ($studentClassId) {
            $cfgGrade = \App\Http\Controllers\Tenant\Admin\GradingConfigsController::gradeForClassTotal($studentClassId, $total);
            if ($cfgGrade !== null) {
                $grade = $cfgGrade;
            }
        }

        DB::table('student_scores')->updateOrInsert(
            [
                'student_id' => (int) $data['student_id'],
                'subject_id' => (int) $data['subject_id'],
                'academic_session_id' => $currentSession->id,
                'term_id' => $currentTerm->id,
            ],
            [
                'ca1' => $data['ca1'],
                'ca2' => $data['ca2'],
                'exam' => $data['exam'],
                'total' => $total,
                'grade' => $grade,
                'remark' => $data['remark'] ?? null,
                'recorded_by' => $request->user()->id,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        // Teacher activity log
        DB::table('teacher_activities')->insert([
            'teacher_id' => $request->user()->id,
            'action' => 'score_saved',
            'metadata' => json_encode([
                'student_id' => (int) $data['student_id'],
                'subject_id' => (int) $data['subject_id'],
                'class_id' => $studentClassId ?: null,
                'total' => $total,
            ]),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 5000),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Bust caches impacted by score updates
        $school = app('tenant.school');
        TenantCache::forgetStudentCaches($school, (int) $data['student_id'], (int) $currentSession->id, (int) $currentTerm->id);
        if ($studentClassId) {
            Cache::forget(TenantCache::teacherScoresListKey((int) $school->id, $studentClassId, (int) $data['subject_id'], (int) $currentSession->id, (int) $currentTerm->id));
        }

        return response()->json(['message' => 'Score saved.']);
    }

    private function gradeFromTotal(int $total): string
    {
        if ($total >= 75) return 'A';
        if ($total >= 65) return 'B';
        if ($total >= 50) return 'C';
        if ($total >= 40) return 'D';
        return 'F';
    }
}


