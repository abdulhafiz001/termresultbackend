<?php

namespace App\Http\Controllers\Tenant\Student;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SubjectsController extends Controller
{
    public function index(Request $request)
    {
        $studentId = $request->user()->id;
        $tenantId = TenantContext::id();

        // Get student's current class
        $studentProfile = TenantDB::table('student_profiles')
            ->where('user_id', $studentId)
            ->first();

        if (!$studentProfile || !$studentProfile->current_class_id) {
            return response()->json(['data' => []]);
        }

        $classId = (int) $studentProfile->current_class_id;

        // Prefer subjects the student offers; fallback to class subjects.
        $subjectIds = [];
        if (Schema::hasTable('student_subject')) {
            $subjectIds = TenantDB::table('student_subject')
                ->where('student_id', $studentId)
                ->pluck('subject_id')
                ->map(fn ($x) => (int) $x)
                ->values()
                ->all();
        }
        if (empty($subjectIds) && Schema::hasTable('class_subject')) {
            $subjectIds = TenantDB::table('class_subject')
                ->where('class_id', $classId)
                ->pluck('subject_id')
                ->map(fn ($x) => (int) $x)
                ->values()
                ->all();
        }

        if (empty($subjectIds)) {
            return response()->json(['data' => []]);
        }

        $currentSession = TenantDB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? TenantDB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        // Get subjects (only the ones the student offers) with teacher info for the student's class.
        // IMPORTANT: Do NOT filter on teacher_class in WHERE, otherwise subjects with no assigned teacher disappear.
        $subjects = TenantDB::table('subjects')
            ->leftJoin('teacher_subject', function ($j) {
                $j->on('subjects.id', '=', 'teacher_subject.subject_id')
                    ->on('teacher_subject.tenant_id', '=', 'subjects.tenant_id');
            })
            ->leftJoin('teacher_class', function ($join) use ($studentProfile) {
                $join->on('teacher_subject.teacher_id', '=', 'teacher_class.teacher_id')
                     ->where('teacher_class.class_id', '=', $studentProfile->current_class_id);
            })
            ->leftJoin('users', function ($j) {
                $j->on('teacher_subject.teacher_id', '=', 'users.id')
                    ->on('users.tenant_id', '=', 'teacher_subject.tenant_id');
            })
            ->leftJoin('classes', function ($j) {
                $j->on('teacher_class.class_id', '=', 'classes.id')
                    ->on('classes.tenant_id', '=', 'teacher_class.tenant_id');
            })
            ->whereIn('subjects.id', $subjectIds)
            ->select([
                'subjects.id',
                'subjects.name',
                'subjects.code',
                'subjects.description',
                'users.name as teacher_name',
                'classes.name as class_name',
            ])
            ->distinct()
            ->orderBy('subjects.name')
            ->get();

        // Attach attendance stats per subject (current session/term), capped at 100%.
        $subjects = $subjects->map(function ($s) use ($tenantId, $studentId, $classId, $currentSession, $currentTerm) {
            $attendance = null;
            if ($currentSession && $currentTerm) {
                $totalDays = (int) TenantDB::table('attendance_sessions')
                    ->where('academic_session_id', $currentSession->id)
                    ->where('term_id', $currentTerm->id)
                    ->where('class_id', $classId)
                    ->where('subject_id', (int) $s->id)
                    ->distinct('date')
                    ->count('date');

                $attended = (int) TenantDB::table('attendance_records')
                    ->join('attendance_sessions', function ($j) {
                        $j->on('attendance_records.attendance_session_id', '=', 'attendance_sessions.id')
                            ->on('attendance_sessions.tenant_id', '=', 'attendance_records.tenant_id');
                    })
                    ->where('attendance_records.student_id', $studentId)
                    ->where('attendance_sessions.academic_session_id', $currentSession->id)
                    ->where('attendance_sessions.term_id', $currentTerm->id)
                    ->where('attendance_sessions.class_id', $classId)
                    ->where('attendance_sessions.subject_id', (int) $s->id)
                    ->whereIn('attendance_records.status', ['present', 'late', 'excused'])
                    ->count();

                $attendance = [
                    'total_days' => $totalDays,
                    'attended_days' => $attended,
                    'rate' => $totalDays > 0 ? round(min(100, ($attended / $totalDays) * 100), 1) : 0,
                ];
            }

            return [
                'id' => (int) $s->id,
                'name' => $s->name,
                'code' => $s->code,
                'description' => $s->description,
                'teacher_name' => $s->teacher_name,
                'class_name' => $s->class_name,
                'attendance' => $attendance,
            ];
        });

        return response()->json(['data' => $subjects]);
    }
}

