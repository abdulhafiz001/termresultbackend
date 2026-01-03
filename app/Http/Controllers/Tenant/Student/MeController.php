<?php

namespace App\Http\Controllers\Tenant\Student;

use App\Http\Controllers\Controller;
use App\Support\TenantDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MeController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        $profile = TenantDB::table('student_profiles')->where('user_id', $user->id)->first();
        $class = $profile?->current_class_id 
            ? TenantDB::table('classes')->where('id', $profile->current_class_id)->first()
            : null;

        // Get current academic session
        $currentSession = TenantDB::table('academic_sessions')
            ->where('is_current', true)
            ->first();

        // Get subjects the student offers (preferred). Fallback to class subjects if none assigned.
        $subjects = collect();
        $offeredSubjectsCount = 0;
        if (Schema::hasTable('student_subject')) {
            $subjects = TenantDB::table('student_subject')
                ->join('subjects', 'student_subject.subject_id', '=', 'subjects.id')
                ->where('student_subject.student_id', $user->id)
                ->select('subjects.id', 'subjects.name', 'subjects.code')
                ->orderBy('subjects.name')
                ->get();
        }

        if ($subjects->count() > 0) {
            $offeredSubjectsCount = $subjects->count();
        } elseif ($class && Schema::hasTable('class_subject')) {
            $subjects = TenantDB::table('class_subject')
                ->join('subjects', 'class_subject.subject_id', '=', 'subjects.id')
                ->where('class_subject.class_id', $class->id)
                ->select('subjects.id', 'subjects.name', 'subjects.code')
                ->orderBy('subjects.name')
                ->get();
            $offeredSubjectsCount = $subjects->count();
        }

        // Teachers assigned to the student's class
        $teachersForClass = [];
        if ($class) {
            $teachersForClass = TenantDB::table('teacher_class')
                ->join('users', 'users.id', '=', 'teacher_class.teacher_id')
                ->where('teacher_class.class_id', $class->id)
                ->where('users.role', 'teacher')
                ->where('users.status', 'active')
                ->select('users.id', 'users.name')
                ->orderBy('users.name')
                ->get();
        }

        // Get academic history (term averages per session)
        $academicHistory = [];
        if ($profile) {
            $sessions = TenantDB::table('academic_sessions')
                ->orderBy('start_date', 'desc')
                ->limit(5)
                ->get();

            foreach ($sessions as $session) {
                $terms = TenantDB::table('terms')
                    ->where('academic_session_id', $session->id)
                    ->get();

                foreach ($terms as $term) {
                    $scores = TenantDB::table('student_scores')
                        ->where('student_id', $user->id)
                        ->where('academic_session_id', $session->id)
                        ->where('term_id', $term->id)
                        ->get();

                    if ($scores->count() > 0) {
                        $avg = $scores->avg('total') ?? 0;
                        
                        // Get position in class if available
                        $position = null;
                        if ($profile->current_class_id) {
                            // Calculate position based on class average
                            $classAverages = TenantDB::table('student_scores')
                                ->join('student_profiles', 'student_scores.student_id', '=', 'student_profiles.user_id')
                                ->where('student_profiles.current_class_id', $profile->current_class_id)
                                ->where('student_scores.academic_session_id', $session->id)
                                ->where('student_scores.term_id', $term->id)
                                ->select('student_scores.student_id', DB::raw('AVG(student_scores.total) as avg_score'))
                                ->groupBy('student_scores.student_id')
                                ->orderByDesc('avg_score')
                                ->get();

                            $position = $classAverages->search(function ($item) use ($user) {
                                return $item->student_id === $user->id;
                            });
                            
                            $position = $position !== false ? $position + 1 : null;
                        }

                        $academicHistory[] = [
                            'session' => $session->name,
                            'term' => $term->name,
                            'class' => $class?->name,
                            'average' => round($avg, 1),
                            'position' => $position,
                            'total_subjects' => $scores->count(),
                        ];
                    }
                }
            }
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'admission_number' => $user->admission_number,
                'role' => $user->role,
            ],
            'profile' => $profile,
            'class' => $class,
            'teachers_for_class' => $teachersForClass,
            'current_session' => $currentSession,
            'subjects' => $subjects,
            'offered_subjects_count' => $offeredSubjectsCount,
            'academic_history' => $academicHistory,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'parent_name' => 'nullable|string|max:255',
            'parent_phone' => 'nullable|string|max:20',
            'parent_email' => 'nullable|email|max:255',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_phone' => 'nullable|string|max:20',
        ]);

        TenantDB::table('student_profiles')
            ->where('user_id', $user->id)
            ->update([
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'parent_name' => $validated['parent_name'] ?? null,
                'parent_phone' => $validated['parent_phone'] ?? null,
                'parent_email' => $validated['parent_email'] ?? null,
                'emergency_contact' => $validated['emergency_contact'] ?? null,
                'emergency_phone' => $validated['emergency_phone'] ?? null,
                'updated_at' => now(),
            ]);

        return response()->json(['message' => 'Profile updated successfully.']);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:4'],
            'confirm_password' => ['required', 'string', 'same:new_password'],
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json(['message' => 'Password changed successfully.']);
    }
}


