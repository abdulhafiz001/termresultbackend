<?php

namespace App\Http\Controllers\Tenant\Student;

use App\Http\Controllers\Controller;
use App\Support\TenantCache;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function stats()
    {
        $user = auth()->user();
        $school = app('tenant.school');
        $tenantId = TenantContext::id();

        $studentProfile = TenantDB::table('student_profiles')->where('user_id', $user->id)->first();
        if (! $studentProfile) {
            return response()->json(['message' => 'Student profile not found'], 404);
        }

        $cacheKey = TenantCache::studentDashboardKey((int) $school->id, (int) $user->id);
        $payload = Cache::remember($cacheKey, now()->addSeconds(60), function () use ($user, $school, $studentProfile, $tenantId) {
            $theme = $school->theme ?? [];
            if (is_string($theme)) {
                $theme = json_decode($theme, true) ?? [];
            }

            // Get class info
            $class = null;
            if (! empty($studentProfile->current_class_id)) {
                $class = TenantDB::table('classes')
                    ->where('id', $studentProfile->current_class_id)
                ->first();
            }

            // Get current session and term
            $currentSession = TenantDB::table('academic_sessions')
                ->where('is_current', true)
                ->first();

            $currentTerm = null;
            if ($currentSession) {
                $currentTerm = TenantDB::table('terms')
                    ->where('academic_session_id', $currentSession->id)
                    ->where('is_current', true)
                    ->first();
            }

            // Total subjects offered by the student (NOT "subjects recorded").
            $subjectsCount = 0;
            if (Schema::hasTable('student_subject')) {
                $subjectsCount = (int) TenantDB::table('student_subject')
                    ->where('student_id', $user->id)
                    ->count();
            }
            if ($subjectsCount === 0 && $class && Schema::hasTable('class_subject')) {
                $subjectsCount = (int) TenantDB::table('class_subject')
                    ->where('class_id', $class->id)
                    ->count();
            }

            // Get recent results
            $recentResults = collect([]);
            if ($currentSession && $currentTerm) {
                $recentResults = DB::table('student_scores')
                    ->where('student_scores.tenant_id', $tenantId)
                    ->join('subjects', function ($j) {
                        $j->on('student_scores.subject_id', '=', 'subjects.id')
                            ->on('subjects.tenant_id', '=', 'student_scores.tenant_id');
                    })
                    ->where('student_scores.student_id', $user->id)
                    ->where('student_scores.academic_session_id', $currentSession->id)
                    ->where('student_scores.term_id', $currentTerm->id)
                    ->select('subjects.name as subject', 'student_scores.total', 'student_scores.grade')
                    ->orderBy('student_scores.created_at', 'desc')
                    ->limit(5)
                    ->get();
            }

            // Calculate average score
            $avgScore = 0;
            if (count($recentResults) > 0) {
                $avgScore = collect($recentResults)->avg('total') ?? 0;
            }

            // Attendance rate (per-day) for current session/term, capped at 100%.
            // IMPORTANT: include subject attendance too (teachers may mark attendance per subject).
            $attendanceRate = null;
            if ($currentSession && $currentTerm && $class) {
                // Total days = distinct dates where ANY attendance was recorded for the class (general OR subject).
                $totalDays = (int) TenantDB::table('attendance_sessions')
                    ->where('academic_session_id', $currentSession->id)
                    ->where('term_id', $currentTerm->id)
                    ->where('class_id', $class->id)
                    ->distinct('date')
                    ->count('date');

                // Attended days = distinct dates where student has at least one attended status for that date.
                // If a student has multiple subject records on the same date, it still counts as ONE day.
                $attendedDays = (int) DB::table('attendance_records')
                    ->where('attendance_records.tenant_id', $tenantId)
                    ->join('attendance_sessions', function ($j) {
                        $j->on('attendance_records.attendance_session_id', '=', 'attendance_sessions.id')
                            ->on('attendance_sessions.tenant_id', '=', 'attendance_records.tenant_id');
                    })
                    ->where('attendance_records.student_id', $user->id)
                    ->where('attendance_sessions.academic_session_id', $currentSession->id)
                    ->where('attendance_sessions.term_id', $currentTerm->id)
                    ->where('attendance_sessions.class_id', $class->id)
                    ->whereIn('attendance_records.status', ['present', 'late', 'excused'])
                    ->distinct('attendance_sessions.date')
                    ->count('attendance_sessions.date');

                $attendanceRate = $totalDays > 0 ? round(min(100, ($attendedDays / $totalDays) * 100), 1) : 0;
            }

            // Get announcements
            $classId = $studentProfile->current_class_id;
            $announcements = TenantDB::table('announcements')
                ->whereNotNull('published_at')
                ->where('for_teachers', false)
                ->where(function ($w) use ($classId) {
                    $w->where('for_all_students', true);

                    if ($classId) {
                        // MySQL/MariaDB JSON query.
                        $w->orWhereRaw('JSON_CONTAINS(class_ids, ?)', [json_encode((int) $classId)]);
                    }
                })
                ->orderByDesc('published_at')
                ->limit(3)
                ->get();

            return [
                'data' => [
                    'school' => [
                        'name' => $school->name,
                        'subdomain' => $school->subdomain,
                        'logo_url' => $theme['logo_url'] ?? (!empty($theme['logo_path']) ? url('storage/' . $theme['logo_path']) : null),
                    ],
                    'student' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'admission_number' => $user->admission_number,
                        'class' => $class ? $class->name : null,
                        'class_id' => $studentProfile->current_class_id,
                    ],
                    'current_session' => $currentSession ? [
                        'id' => $currentSession->id,
                        'name' => $currentSession->name,
                    ] : null,
                    'current_term' => $currentTerm ? [
                        'id' => $currentTerm->id,
                        'name' => $currentTerm->name,
                    ] : null,
                    'stats' => [
                        'total_subjects' => $subjectsCount,
                        'average_score' => round($avgScore, 1),
                        'class_position' => null, // Calculated separately
                        'attendance' => $attendanceRate,
                    ],
                    'recent_results' => $recentResults->map(function ($result) {
                        return [
                            'subject' => $result->subject,
                            'score' => $result->total,
                            'grade' => $result->grade,
                        ];
                    }),
                    'announcements' => $announcements->map(function ($a) {
                        return [
                            'id' => $a->id,
                            'title' => $a->title,
                            'content' => $a->body,
                            'published_at' => $a->published_at,
                        ];
                    }),
                ],
            ];
        });

        return response()->json($payload);
    }
}

