<?php

namespace App\Http\Controllers\Tenant\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats()
    {
        $user = auth()->user();
        
        $school = app('tenant.school');
        $theme = $school->theme ?? [];
        if (is_string($theme)) {
            $theme = json_decode($theme, true) ?? [];
        }

        // Get assigned classes
        $assignedClasses = DB::table('teacher_class')
            ->join('classes', 'teacher_class.class_id', '=', 'classes.id')
            ->where('teacher_class.teacher_id', $user->id)
            ->select('classes.*')
            ->get();

        // Get assigned subjects
        $assignedSubjects = DB::table('teacher_subject')
            ->join('subjects', 'teacher_subject.subject_id', '=', 'subjects.id')
            ->where('teacher_subject.teacher_id', $user->id)
            ->select('subjects.*')
            ->get();

        // Count total students across assigned classes
        $totalStudents = 0;
        $classIds = $assignedClasses->pluck('id')->toArray();
        if (count($classIds) > 0) {
            $totalStudents = DB::table('student_profiles')
                ->join('users', 'users.id', '=', 'student_profiles.user_id')
                ->where('users.role', 'student')
                ->whereIn('student_profiles.current_class_id', $classIds)
                ->count();
        }

        // Get current session and term
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

        // Count pending scores (classes/subjects where results not yet entered)
        $pendingScores = 0;
        // This would require more complex logic to calculate

        // Get form class if teacher is form teacher
        $formClass = DB::table('classes')
            ->where('form_teacher_id', $user->id)
            ->first();

        // Get recent activities
        $recentActivities = [];

        return response()->json([
            'data' => [
                'school' => [
                    'name' => $school->name,
                    'subdomain' => $school->subdomain,
                    'logo_url' => $theme['logo_url'] ?? (!empty($theme['logo_path']) ? url('storage/' . $theme['logo_path']) : null),
                ],
                'teacher' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'employee_id' => $user->username ?? null,
                    'email' => $user->email,
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
                    'total_students' => $totalStudents,
                    'total_classes' => count($assignedClasses),
                    'total_subjects' => count($assignedSubjects),
                    'pending_scores' => $pendingScores,
                ],
                'assigned_classes' => $assignedClasses->map(function ($class) {
                    $studentCount = DB::table('student_profiles')
                        ->join('users', 'users.id', '=', 'student_profiles.user_id')
                        ->where('users.role', 'student')
                        ->where('student_profiles.current_class_id', $class->id)
                        ->count();
                    return [
                        'id' => $class->id,
                        'name' => $class->name,
                        'students' => $studentCount,
                    ];
                }),
                'assigned_subjects' => $assignedSubjects->map(function ($subject) {
                    return [
                        'id' => $subject->id,
                        'name' => $subject->name,
                        'code' => $subject->code ?? null,
                    ];
                }),
                'form_class' => $formClass ? [
                    'id' => $formClass->id,
                    'name' => $formClass->name,
                ] : null,
            ],
        ]);
    }
}

