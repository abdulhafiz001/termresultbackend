<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Support\TenantDB;
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
        $totalStudents = TenantDB::table('users')->where('role', 'student')->count();
        $totalTeachers = TenantDB::table('users')->where('role', 'teacher')->count();
        $totalClasses = TenantDB::table('classes')->count();
        $totalSubjects = TenantDB::table('subjects')->count();

        // Get current session/term
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

        // Get recent activities from various sources
        $recentActivities = collect();
        
        // Get recently created students (last 7 days)
        $recentStudents = TenantDB::table('users as u')
            ->leftJoin('student_profiles as sp', function ($j) {
                $j->on('sp.user_id', '=', 'u.id')
                  ->on('sp.tenant_id', '=', 'u.tenant_id');
            })
            ->where('u.role', 'student')
            ->where('u.created_at', '>=', now()->subDays(7))
            ->orderByDesc('u.created_at')
            ->limit(5)
            ->get([
                'u.id',
                'u.created_at',
                'sp.first_name',
                'sp.last_name',
                'u.admission_number',
            ])
            ->map(function ($student) {
                $name = trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? ''));
                return [
                    'id' => 'student_' . $student->id,
                    'action' => 'student_registered',
                    'description' => 'New student registered: ' . ($name ?: $student->admission_number),
                    'user' => 'System',
                    'created_at' => $student->created_at,
                ];
            });
        
        // Get recently created teachers (last 7 days)
        $recentTeachers = TenantDB::table('users')
            ->where('role', 'teacher')
            ->where('created_at', '>=', now()->subDays(7))
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'username', 'created_at'])
            ->map(function ($teacher) {
                return [
                    'id' => 'teacher_' . $teacher->id,
                    'action' => 'teacher_added',
                    'description' => 'New teacher added: ' . $teacher->username,
                    'user' => 'System',
                    'created_at' => $teacher->created_at,
                ];
            });
        
        // Get recently created classes (last 7 days)
        $recentClasses = TenantDB::table('classes')
            ->where('created_at', '>=', now()->subDays(7))
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'name', 'created_at'])
            ->map(function ($class) {
                return [
                    'id' => 'class_' . $class->id,
                    'action' => 'class_created',
                    'description' => 'New class created: ' . $class->name,
                    'user' => 'System',
                    'created_at' => $class->created_at,
                ];
            });
        
        // Merge all activities and sort by date
        $recentActivities = $recentStudents
            ->concat($recentTeachers)
            ->concat($recentClasses)
            ->sortByDesc('created_at')
            ->take(10)
            ->values();

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
                'recent_activities' => $recentActivities->toArray(),
            ],
        ]);
    }
}

