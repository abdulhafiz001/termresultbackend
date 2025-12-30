<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats()
    {
        $school = app('tenant.school');
        $theme = $school->theme ?? [];
        if (is_string($theme)) {
            $theme = json_decode($theme, true) ?? [];
        }

        // Get counts
        // NOTE: In tenant DB, students/teachers are stored in `users` with roles, plus `student_profiles`.
        $totalStudents = DB::table('users')->where('role', 'student')->count();
        $totalTeachers = DB::table('users')->where('role', 'teacher')->count();
        $totalClasses = DB::table('classes')->count();
        $totalSubjects = DB::table('subjects')->count();

        // Get current session/term
        $currentSession = DB::table('academic_sessions')
            ->where('is_current', true)
            ->first();

        $currentTerm = null;
        if ($currentSession) {
            $currentTerm = DB::table('terms')
                ->where('academic_session_id', $currentSession->id)
                ->where('is_current', true)
                ->first();
        }

        // Recent activities are not yet tracked per-tenant (avoid querying central audit_logs from tenant connection).
        $recentActivities = collect();

        return response()->json([
            'data' => [
                'school' => [
                    'name' => $school->name,
                    'subdomain' => $school->subdomain,
                    'logo_url' => $theme['logo_url'] ?? (!empty($theme['logo_path']) ? url('storage/' . $theme['logo_path']) : null),
                ],
                'stats' => [
                    'total_students' => $totalStudents,
                    'total_teachers' => $totalTeachers,
                    'total_classes' => $totalClasses,
                    'total_subjects' => $totalSubjects,
                ],
                'current_session' => $currentSession ? [
                    'id' => $currentSession->id,
                    'name' => $currentSession->name,
                    'start_date' => $currentSession->start_date,
                    'end_date' => $currentSession->end_date,
                ] : null,
                'current_term' => $currentTerm ? [
                    'id' => $currentTerm->id,
                    'name' => $currentTerm->name,
                    'start_date' => $currentTerm->start_date,
                    'end_date' => $currentTerm->end_date,
                ] : null,
                'recent_activities' => $recentActivities->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'action' => $activity->action ?? 'Activity',
                        'description' => $activity->description ?? '',
                        'user' => $activity->user_name ?? 'System',
                        'created_at' => $activity->created_at,
                    ];
                }),
            ],
        ]);
    }
}

