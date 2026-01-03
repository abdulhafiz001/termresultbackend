<?php

namespace App\Http\Controllers\Tenant\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Admin\GradingConfigsController;
use App\Support\TenantDB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ReportCardController extends Controller
{
    public function download(Request $request)
    {
        $data = $request->validate([
            'academic_session_id' => ['required', 'integer'],
            'term_id' => ['required', 'integer'],
        ]);

        $studentId = (int) $request->user()->id;
        $sessionId = (int) $data['academic_session_id'];
        $termId = (int) $data['term_id'];

        $profile = TenantDB::table('student_profiles')->where('user_id', $studentId)->first();
        $classId = (int) ($profile->current_class_id ?? 0);
        $className = $classId ? (string) (TenantDB::table('classes')->where('id', $classId)->value('name') ?? '') : '';

        $academicSession = TenantDB::table('academic_sessions')->where('id', $sessionId)->first();
        $termRow = TenantDB::table('terms')->where('id', $termId)->where('academic_session_id', $sessionId)->first();

        if (! $academicSession || ! $termRow) {
            return response()->json(['message' => 'Academic session/term not found.'], 404);
        }

        // School branding from central
        $school = app('tenant.school');
        $theme = $school->theme ?? [];
        if (is_string($theme)) $theme = json_decode($theme, true) ?? [];
        $featureToggles = $school->feature_toggles ?? [];
        if (is_string($featureToggles)) $featureToggles = json_decode($featureToggles, true) ?? [];

        $brandPrimary = $theme['primary'] ?? '#2563eb';
        $logoDiskPath = null;
        if (! empty($theme['logo_path'])) {
            $logoDiskPath = Storage::disk('public')->path($theme['logo_path']);
        }

        // Positions policy
        $policy = 'all';
        $rp = $featureToggles['results_positions'] ?? null;
        if (is_array($rp)) {
            $globalMode = strtolower((string) ($rp['global_mode'] ?? 'all'));
            $top3Ids = array_map('intval', (array) ($rp['top3_only_class_ids'] ?? []));
            $noneIds = array_map('intval', (array) ($rp['no_positions_class_ids'] ?? []));
            if ($globalMode === 'none') $policy = 'none';
            elseif ($classId && in_array($classId, $noneIds, true)) $policy = 'none';
            elseif ($classId && in_array($classId, $top3Ids, true)) $policy = 'top3';
        }

        $scores = TenantDB::table('student_scores as sc')
            ->join('subjects as sub', function ($j) {
                $j->on('sub.id', '=', 'sc.subject_id')
                    ->on('sub.tenant_id', '=', 'sc.tenant_id');
            })
            ->where('sc.student_id', $studentId)
            ->where('sc.academic_session_id', $sessionId)
            ->where('sc.term_id', $termId)
            ->orderBy('sub.name')
            ->get([
                'sub.id as subject_id',
                'sub.name as subject_name',
                'sc.ca1',
                'sc.ca2',
                'sc.exam',
                'sc.total',
                'sc.grade',
                'sc.remark',
            ]);

        // Ensure report card can only be generated when ALL subjects the student offers have been recorded.
        // Prefer student_subject (student-specific), fallback to class_subject.
        $expectedSubjectIds = [];
        if (Schema::hasTable('student_subject')) {
            $expectedSubjectIds = TenantDB::table('student_subject')
                ->where('student_id', $studentId)
                ->pluck('subject_id')
                ->map(fn ($x) => (int) $x)
                ->values()
                ->all();
        }
        if (empty($expectedSubjectIds) && $classId && Schema::hasTable('class_subject')) {
            $expectedSubjectIds = TenantDB::table('class_subject')
                ->where('class_id', $classId)
                ->pluck('subject_id')
                ->map(fn ($x) => (int) $x)
                ->values()
                ->all();
        }

        $expectedCount = count($expectedSubjectIds);
        if ($expectedCount <= 0) {
            return response()->json([
                'message' => 'Report card is not available because your subjects have not been configured by the school.',
            ], 422);
        }

        $recordedSubjectIds = TenantDB::table('student_scores')
            ->where('student_id', $studentId)
            ->where('academic_session_id', $sessionId)
            ->where('term_id', $termId)
            ->whereIn('subject_id', $expectedSubjectIds)
            ->whereNotNull('total')
            ->pluck('subject_id')
            ->map(fn ($x) => (int) $x)
            ->unique()
            ->values()
            ->all();

        $recordedCount = count($recordedSubjectIds);
        if ($recordedCount < $expectedCount) {
            $missingIds = array_values(array_diff($expectedSubjectIds, $recordedSubjectIds));
            $missingSubjects = [];
            if (!empty($missingIds)) {
                $missingSubjects = TenantDB::table('subjects')
                    ->whereIn('id', $missingIds)
                    ->orderBy('name')
                    ->pluck('name')
                    ->values()
                    ->all();
            }

            return response()->json([
                'message' => 'Report card is not ready yet. Some subjects have not been recorded.',
                'data' => [
                    'expected_subjects' => $expectedCount,
                    'recorded_subjects' => $recordedCount,
                    'missing_subjects' => $missingSubjects,
                ],
            ], 422);
        }

        // Grading config for the student's class (dynamic grading scale on PDF).
        $gradingConfig = null;
        $gradingRanges = collect();
        if ($classId) {
            $gradingConfig = TenantDB::table('grading_configs as gc')
                ->join('grading_config_classes as gcc', function ($j) {
                    $j->on('gcc.grading_config_id', '=', 'gc.id')
                        ->on('gcc.tenant_id', '=', 'gc.tenant_id');
                })
                ->where('gc.is_active', true)
                ->where('gcc.class_id', $classId)
                ->orderByDesc('gc.id')
                ->select(['gc.id', 'gc.name'])
                ->first();

            if ($gradingConfig) {
                $gradingRanges = TenantDB::table('grading_config_ranges')
                    ->where('grading_config_id', $gradingConfig->id)
                    ->orderByDesc('min_score')
                    ->get(['grade', 'min_score', 'max_score']);
            }
        }

        $totalScore = $scores->sum('total');
        $averageScore = $scores->count() > 0 ? ($totalScore / $scores->count()) : 0;

        // Overall position in class for this term (by average total)
        $overallPosition = null;
        $overallPositionFormatted = null;
        $totalStudentsInClass = null;
        if ($policy !== 'none' && $classId) {
            $ranked = TenantDB::table('student_scores as sc')
                ->join('student_profiles as sp', function ($j) {
                    $j->on('sp.user_id', '=', 'sc.student_id')
                        ->on('sp.tenant_id', '=', 'sc.tenant_id');
                })
                ->where('sp.current_class_id', $classId)
                ->where('sc.academic_session_id', $sessionId)
                ->where('sc.term_id', $termId)
                ->whereNotNull('sc.total')
                ->groupBy('sc.student_id')
                ->selectRaw('sc.student_id, AVG(sc.total) as avg_total')
                ->orderByDesc('avg_total')
                ->get();

            $totalStudentsInClass = $ranked->count();
            $pos = 1;
            foreach ($ranked as $r) {
                if ((int) $r->student_id === $studentId) {
                    $overallPosition = $pos;
                    break;
                }
                $pos++;
            }
            $overallPositionFormatted = $overallPosition ? $this->ordinal($overallPosition) : null;
        }

        $hidePos = function (?int $pos) use ($policy): bool {
            if ($policy === 'none') return true;
            if ($policy === 'top3') return $pos === null || $pos > 3;
            return false;
        };
        if ($hidePos($overallPosition)) {
            $overallPosition = null;
            $overallPositionFormatted = null;
        }

        // Subject positions
        $scores = $scores->map(function ($s) use ($studentId, $classId, $sessionId, $termId, $hidePos) {
            $pos = null;
            if ($classId) {
                $ranked = TenantDB::table('student_scores as sc')
                    ->join('student_profiles as sp', function ($j) {
                        $j->on('sp.user_id', '=', 'sc.student_id')
                            ->on('sp.tenant_id', '=', 'sc.tenant_id');
                    })
                    ->where('sp.current_class_id', $classId)
                    ->where('sc.academic_session_id', $sessionId)
                    ->where('sc.term_id', $termId)
                    ->where('sc.subject_id', (int) $s->subject_id)
                    ->whereNotNull('sc.total')
                    ->orderByDesc('sc.total')
                    ->get(['sc.student_id']);

                $i = 1;
                foreach ($ranked as $r) {
                    if ((int) $r->student_id === $studentId) {
                        $pos = $i;
                        break;
                    }
                    $i++;
                }
            }

            $totalInt = is_numeric($s->total) ? (int) round((float) $s->total) : null;
            $dynamicGrade = $classId ? GradingConfigsController::gradeForClassTotal($classId, $totalInt) : null;

            return (object) [
                'subject_name' => $s->subject_name,
                'first_ca' => $s->ca1,
                'second_ca' => $s->ca2,
                'exam_score' => $s->exam,
                'total_score' => $s->total,
                'grade' => $dynamicGrade ?: $s->grade,
                'remark' => $s->remark,
                'subject_position_formatted' => (!$hidePos($pos) && $pos) ? $this->ordinal($pos) : null,
            ];
        });

        // Promotion status (third term)
        $promotionStatus = null;
        $isThirdTerm = strtolower(trim((string) $termRow->name)) === 'third term';
        if ($isThirdTerm) {
            $promotion = TenantDB::table('student_promotions')
                ->where('student_id', $studentId)
                ->where('academic_session_id', $sessionId)
                ->where('term_id', $termId)
                ->value('status');
            $promotionStatus = $promotion ?: null;
        }

        // Final average (for 3rd term) = (T1 + T2 + T3)/3
        $thirdTermFinalAverage = null;
        if ($isThirdTerm) {
            $firstId = TenantDB::table('terms')->where('academic_session_id', $sessionId)->whereRaw('LOWER(TRIM(name)) = ?', ['first term'])->value('id');
            $secondId = TenantDB::table('terms')->where('academic_session_id', $sessionId)->whereRaw('LOWER(TRIM(name)) = ?', ['second term'])->value('id');

            $termAvg = function (?int $tid) use ($studentId, $sessionId): ?float {
                if (! $tid) return null;
                $rows = TenantDB::table('student_scores')
                    ->where('student_id', $studentId)
                    ->where('academic_session_id', $sessionId)
                    ->where('term_id', $tid)
                    ->whereNotNull('total')
                    ->get(['total']);
                if ($rows->count() === 0) return null;
                return $rows->sum('total') / $rows->count();
            };

            $a1 = $termAvg($firstId);
            $a2 = $termAvg($secondId);
            $a3 = $termAvg($termId);
            if ($a1 !== null && $a2 !== null && $a3 !== null) {
                $thirdTermFinalAverage = [
                    'first_term_average' => $a1,
                    'second_term_average' => $a2,
                    'third_term_average' => $a3,
                    'final_average' => ($a1 + $a2 + $a3) / 3.0,
                ];
            }
        }

        // Verification code (HMAC-signed, school-specific)
        $payload = json_encode([
            'school_id' => $school->id,
            'student_id' => $studentId,
            'academic_session_id' => $sessionId,
            'term_id' => $termId,
            'generated_at' => now()->toISOString(),
        ]);
        $payloadB64 = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $payloadB64, (string) config('app.key'));
        $verificationCode = $payloadB64 . '.' . substr($sig, 0, 16);

        $schoolInfo = [
            'name' => $school->name,
            'address' => $school->address,
            'phone' => $school->contact_phone,
            'email' => $school->contact_email,
            'logo_path' => $logoDiskPath,
        ];

        $student = (object) [
            'first_name' => $profile->first_name ?? '',
            'middle_name' => $profile->middle_name ?? '',
            'last_name' => $profile->last_name ?? '',
            'admission_number' => $request->user()->admission_number,
        ];

        $pdf = Pdf::loadView('report.student-report-card', [
            'brandPrimary' => $brandPrimary,
            'schoolInfo' => $schoolInfo,
            'student' => $student,
            'className' => $className ?: 'N/A',
            'academicSessionName' => $academicSession->name,
            'termName' => $termRow->name,
            'scores' => $scores,
            'totalScore' => $totalScore,
            'averageScore' => $averageScore,
            'overallPositionFormatted' => $overallPositionFormatted,
            'promotionStatus' => $promotionStatus,
            'isThirdTerm' => $isThirdTerm,
            'thirdTermFinalAverage' => $thirdTermFinalAverage,
            'positionsPolicy' => $policy,
            'verificationCode' => $verificationCode,
            'gradingConfig' => $gradingConfig,
            'gradingRanges' => $gradingRanges,
        ]);

        $safeStudent = trim(($student->last_name ?: '') . '-' . ($student->first_name ?: 'student'));
        $safeStudent = preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower($safeStudent));
        $safeClass = preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower((string) $className));
        $safeTerm = preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower((string) $termRow->name));
        $safeSession = preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower((string) $academicSession->name));

        $filename = "{$safeStudent}-{$safeClass}-{$safeSession}-{$safeTerm}-report-card.pdf";

        return $pdf->download($filename);
    }

    private function ordinal(int $n): string
    {
        $suffix = 'th';
        if ($n % 100 < 11 || $n % 100 > 13) {
            $suffix = match ($n % 10) {
                1 => 'st',
                2 => 'nd',
                3 => 'rd',
                default => 'th',
            };
        }
        return $n . $suffix;
    }
}


