<?php

namespace App\Http\Controllers\Tenant\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Admin\GradingConfigsController;
use App\Support\TenantCache;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResultsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tenantId = TenantContext::id();
        $restrictions = $user?->restrictions ?? [];
        if (is_string($restrictions)) {
            $restrictions = json_decode($restrictions, true) ?? [];
        }

        if (($user?->status ?? 'active') === 'disabled') {
            return response()->json([
                'message' => 'Account is disabled.',
                'reason' => $user?->restriction_reason,
            ], 403);
        }
        if (($user?->status ?? 'active') === 'restricted' && in_array('results', (array) $restrictions, true)) {
            return response()->json([
                'message' => 'Results are restricted for this account.',
                'reason' => $user?->restriction_reason,
            ], 403);
        }

        $studentId = $request->user()->id;

        $currentSession = TenantDB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? TenantDB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        $sessionId = (int) $request->query('academic_session_id', $currentSession->id ?? 0);
        $termId = (int) $request->query('term_id', $currentTerm->id ?? 0);

        if (! $sessionId || ! $termId) {
            return response()->json(['message' => 'Academic session/term not set.'], 400);
        }

        $profile = TenantDB::table('student_profiles')->where('user_id', $studentId)->first();
        $classId = (int) ($profile->current_class_id ?? 0);

        $school = app('tenant.school');
        $theme = $school->theme ?? [];
        if (is_string($theme)) $theme = json_decode($theme, true) ?? [];
        $featureToggles = $school->feature_toggles ?? [];
        if (is_string($featureToggles)) $featureToggles = json_decode($featureToggles, true) ?? [];

        $logoUrl = null;
        if (!empty($theme['logo_path'])) {
            $logoUrl = url('storage/' . $theme['logo_path']);
        } elseif (!empty($theme['logo_url'])) {
            $logoUrl = str_starts_with($theme['logo_url'], 'http')
                ? $theme['logo_url']
                : url('storage/' . $theme['logo_url']);
        }

        // Resolve positions policy for this class.
        $policy = 'all'; // all|top3|none
        $rp = $featureToggles['results_positions'] ?? null;
        if (is_array($rp)) {
            $globalMode = strtolower((string) ($rp['global_mode'] ?? 'all'));
            $top3Ids = array_map('intval', (array) ($rp['top3_only_class_ids'] ?? []));
            $noneIds = array_map('intval', (array) ($rp['no_positions_class_ids'] ?? []));

            if ($globalMode === 'none') {
                $policy = 'none';
            } elseif ($classId && in_array($classId, $noneIds, true)) {
                $policy = 'none';
            } elseif ($classId && in_array($classId, $top3Ids, true)) {
                $policy = 'top3';
            }
        }

        $cacheKey = TenantCache::studentResultsKey((int) $school->id, (int) $studentId, (int) $sessionId, (int) $termId);

        $payload = Cache::remember($cacheKey, now()->addSeconds(120), function () use (
            $studentId,
            $sessionId,
            $termId,
            $classId,
            $policy,
            $school,
            $theme,
            $logoUrl
        ) {
            $rows = TenantDB::table('student_scores')
                ->join('subjects', function ($j) {
                    $j->on('subjects.id', '=', 'student_scores.subject_id')
                        ->on('subjects.tenant_id', '=', 'student_scores.tenant_id');
                })
                ->where('student_scores.student_id', $studentId)
                ->where('student_scores.academic_session_id', $sessionId)
                ->where('student_scores.term_id', $termId)
                ->select([
                    'student_scores.subject_id',
                    'subjects.name as subject_name',
                    'student_scores.ca1',
                    'student_scores.ca2',
                    'student_scores.exam',
                    'student_scores.total',
                    'student_scores.grade',
                    'student_scores.remark',
                ])
                ->orderBy('subjects.name')
                ->get();

            // Overall class ranking (by average total) for this term.
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

                if ($overallPosition !== null) {
                    $suffix = 'th';
                    if ($overallPosition % 100 < 11 || $overallPosition % 100 > 13) {
                        $suffix = match ($overallPosition % 10) {
                            1 => 'st',
                            2 => 'nd',
                            3 => 'rd',
                            default => 'th',
                        };
                    }
                    $overallPositionFormatted = $overallPosition . $suffix;
                }
            }

            // Subject positions (rank within class per subject).
            $subjectPositions = [];
            if ($policy !== 'none' && $classId && $rows->count() > 0) {
                foreach ($rows as $row) {
                    $subjectId = (int) $row->subject_id;
                    $ranked = TenantDB::table('student_scores as sc')
                        ->join('student_profiles as sp', function ($j) {
                            $j->on('sp.user_id', '=', 'sc.student_id')
                                ->on('sp.tenant_id', '=', 'sc.tenant_id');
                        })
                        ->where('sp.current_class_id', $classId)
                        ->where('sc.academic_session_id', $sessionId)
                        ->where('sc.term_id', $termId)
                        ->where('sc.subject_id', $subjectId)
                        ->whereNotNull('sc.total')
                        ->orderByDesc('sc.total')
                        ->get(['sc.student_id']);

                    $pos = 1;
                    foreach ($ranked as $r) {
                        if ((int) $r->student_id === $studentId) {
                            $subjectPositions[$subjectId] = $pos;
                            break;
                        }
                        $pos++;
                    }
                }
            }

            // Apply top3 policy (hide positions for non-top3).
            $shouldHidePositions = function (?int $pos) use ($policy): bool {
                if ($policy === 'none') return true;
                if ($policy === 'top3') return $pos === null || $pos > 3;
                return false;
            };

            $rows = $rows->map(function ($r) use ($subjectPositions, $shouldHidePositions, $classId) {
                $pos = $subjectPositions[(int) $r->subject_id] ?? null;
                $formatted = null;
                if (!$shouldHidePositions($pos) && $pos !== null) {
                    $suffix = 'th';
                    if ($pos % 100 < 11 || $pos % 100 > 13) {
                        $suffix = match ($pos % 10) {
                            1 => 'st',
                            2 => 'nd',
                            3 => 'rd',
                            default => 'th',
                        };
                    }
                    $formatted = $pos . $suffix;
                }

                $totalInt = is_numeric($r->total) ? (int) round((float) $r->total) : null;
                $dynamicGrade = ($classId && $totalInt !== null) ? GradingConfigsController::gradeForClassTotal($classId, $totalInt) : null;

                return [
                    'subject_id' => (int) $r->subject_id,
                    'subject_name' => $r->subject_name,
                    'ca1' => $r->ca1,
                    'ca2' => $r->ca2,
                    'exam' => $r->exam,
                    'total' => $r->total,
                    'grade' => $dynamicGrade ?: $r->grade,
                    'remark' => $r->remark,
                    'subject_position' => $pos,
                    'subject_position_formatted' => $formatted,
                ];
            });

            $term = TenantDB::table('terms')->where('id', $termId)->first();
            $promotion = null;
            if ($term && strtolower(trim((string) $term->name)) === 'third term') {
                $promotion = TenantDB::table('student_promotions as sp')
                    ->leftJoin('classes as c', function ($j) {
                        $j->on('c.id', '=', 'sp.to_class_id')
                            ->on('c.tenant_id', '=', 'sp.tenant_id');
                    })
                    ->where('sp.student_id', $studentId)
                    ->where('sp.academic_session_id', $sessionId)
                    ->where('sp.term_id', $termId)
                    ->select(['sp.status', 'sp.to_class_id', 'c.name as to_class_name'])
                    ->first();
            }

            $hideOverall = $shouldHidePositions($overallPosition);
            if ($hideOverall) {
                $overallPosition = null;
                $overallPositionFormatted = null;
            }

            return [
                'meta' => [
                    'academic_session_id' => $sessionId,
                    'term_id' => $termId,
                    'positions_policy' => $policy,
                    'overall_position' => $overallPosition,
                    'overall_position_formatted' => $overallPositionFormatted,
                    'total_students_in_class' => $totalStudentsInClass,
                    'promotion' => $promotion ? [
                        'status' => $promotion->status,
                        'to_class_id' => $promotion->to_class_id,
                        'to_class_name' => $promotion->to_class_name,
                    ] : null,
                ],
                'school' => [
                    'name' => $school->name,
                    'logo_url' => $logoUrl,
                    'theme' => [
                        'primary' => $theme['primary'] ?? '#2563eb',
                        'secondary' => $theme['secondary'] ?? '#0ea5e9',
                    ],
                ],
                'data' => $rows,
            ];
        });

        return response()->json($payload);
    }
}


