<?php

namespace App\Http\Controllers\Tenant\Teacher;

use App\Http\Controllers\Controller;
use App\Support\TenantCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'date' => ['required', 'date'],
        ]);

        $currentSession = DB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? DB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        if (! $currentSession || ! $currentTerm) {
            return response()->json(['message' => 'Current academic session/term is not set.'], 400);
        }

        $subjectId = isset($data['subject_id']) ? (int) $data['subject_id'] : null;

        $session = DB::table('attendance_sessions')
            ->where('class_id', (int) $data['class_id'])
            ->where('subject_id', $subjectId)
            ->where('academic_session_id', $currentSession->id)
            ->where('term_id', $currentTerm->id)
            ->where('date', $data['date'])
            ->first();

        if (! $session) {
            return response()->json(['data' => []]);
        }

        $records = DB::table('attendance_records')
            ->where('attendance_session_id', $session->id)
            ->get();

        return response()->json(['data' => $records]);
    }

    public function store(Request $request)
    {
        $teacherId = $request->user()->id;
        
        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'date' => ['required', 'date'],
            'week' => ['nullable', 'integer', 'min:1', 'max:52'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.student_id' => ['required', 'integer', 'exists:users,id'],
            'records.*.status' => ['required', 'in:present,absent,late,excused'],
        ]);

        $currentSession = DB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? DB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        if (! $currentSession || ! $currentTerm) {
            return response()->json(['message' => 'Current academic session/term is not set.'], 400);
        }

        $classId = (int) $data['class_id'];
        $subjectId = isset($data['subject_id']) ? (int) $data['subject_id'] : null;
        $date = $data['date'];

        // Verify teacher is assigned to this class and subject (if subject provided)
        if ($subjectId) {
            $isAssigned = DB::table('teacher_class')
                ->join('teacher_subject', function ($join) use ($teacherId, $subjectId) {
                    $join->on('teacher_class.teacher_id', '=', 'teacher_subject.teacher_id')
                         ->where('teacher_subject.teacher_id', $teacherId)
                         ->where('teacher_subject.subject_id', $subjectId);
                })
                ->where('teacher_class.class_id', $classId)
                ->where('teacher_class.teacher_id', $teacherId)
                ->exists();

            if (! $isAssigned) {
                return response()->json(['message' => 'You are not assigned to teach this subject in this class.'], 403);
            }
        } else {
            // For general class attendance, verify teacher is form teacher or assigned to class
            $isFormTeacher = DB::table('classes')
                ->where('id', $classId)
                ->where('form_teacher_id', $teacherId)
                ->exists();

            $isAssignedToClass = DB::table('teacher_class')
                ->where('class_id', $classId)
                ->where('teacher_id', $teacherId)
                ->exists();

            if (! $isFormTeacher && ! $isAssignedToClass) {
                return response()->json(['message' => 'You can only mark attendance for classes you are assigned to.'], 403);
            }
        }

        $sessionId = null;
        DB::transaction(function () use ($request, $currentSession, $currentTerm, $data, $classId, $subjectId, $date, &$sessionId) {
            $existing = DB::table('attendance_sessions')
                ->where('class_id', $classId)
                ->where('subject_id', $subjectId)
                ->where('academic_session_id', $currentSession->id)
                ->where('term_id', $currentTerm->id)
                ->where('date', $date)
                ->first();

            if ($existing) {
                $sessionId = $existing->id;
                DB::table('attendance_sessions')->where('id', $sessionId)->update([
                    'week' => $data['week'] ?? $existing->week,
                    'updated_at' => now(),
                ]);
            } else {
                $sessionId = DB::table('attendance_sessions')->insertGetId([
                    'class_id' => $classId,
                    'subject_id' => $subjectId,
                    'academic_session_id' => $currentSession->id,
                    'term_id' => $currentTerm->id,
                    'date' => $date,
                    'week' => $data['week'] ?? null,
                    'recorded_by' => $request->user()->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($data['records'] as $r) {
                DB::table('attendance_records')->updateOrInsert(
                    ['attendance_session_id' => $sessionId, 'student_id' => (int) $r['student_id']],
                    ['status' => $r['status'], 'updated_at' => now(), 'created_at' => now()]
                );
            }
        });

        // Bust student caches so dashboards update immediately (attendance affects dashboard attendance rate).
        try {
            $school = app('tenant.school');
            $sid = (int) $currentSession->id;
            $tid = (int) $currentTerm->id;
            foreach (($data['records'] ?? []) as $r) {
                $studentId = (int) ($r['student_id'] ?? 0);
                if ($studentId > 0) {
                    TenantCache::forgetStudentCaches($school, $studentId, $sid, $tid);
                }
            }
        } catch (\Throwable $e) {
            // ignore cache bust failures
        }

        // Guard: some tenants may not have teacher_activities yet.
        if (Schema::hasTable('teacher_activities')) {
            DB::table('teacher_activities')->insert([
                'teacher_id' => $request->user()->id,
                'action' => 'attendance_saved',
                'metadata' => json_encode([
                    'class_id' => $classId,
                    'subject_id' => $subjectId,
                    'date' => $date,
                    'week' => $data['week'] ?? null,
                    'records_count' => count($data['records'] ?? []),
                ]),
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 5000),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Attendance saved.']);
    }
}


