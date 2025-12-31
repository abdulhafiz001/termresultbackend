<?php

namespace App\Http\Controllers\Tenant\Teacher;

use App\Http\Controllers\Controller;
use App\Support\TenantCache;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = TenantContext::id();

        $data = $request->validate([
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')->where('tenant_id', $tenantId)],
            'subject_id' => ['nullable', 'integer', Rule::exists('subjects', 'id')->where('tenant_id', $tenantId)],
            'date' => ['required', 'date'],
        ]);

        $currentSession = TenantDB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? TenantDB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        if (! $currentSession || ! $currentTerm) {
            return response()->json(['message' => 'Current academic session/term is not set.'], 400);
        }

        $subjectId = isset($data['subject_id']) ? (int) $data['subject_id'] : null;

        $session = TenantDB::table('attendance_sessions')
            ->where('class_id', (int) $data['class_id'])
            ->where('subject_id', $subjectId)
            ->where('academic_session_id', $currentSession->id)
            ->where('term_id', $currentTerm->id)
            ->where('date', $data['date'])
            ->first();

        if (! $session) {
            return response()->json(['data' => []]);
        }

        $records = TenantDB::table('attendance_records')
            ->where('attendance_session_id', $session->id)
            ->get();

        return response()->json(['data' => $records]);
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();
        $teacherId = $request->user()->id;
        
        $data = $request->validate([
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')->where('tenant_id', $tenantId)],
            'subject_id' => ['nullable', 'integer', Rule::exists('subjects', 'id')->where('tenant_id', $tenantId)],
            'date' => ['required', 'date'],
            'week' => ['nullable', 'integer', 'min:1', 'max:52'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.student_id' => ['required', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'records.*.status' => ['required', 'in:present,absent,late,excused'],
        ]);

        $currentSession = TenantDB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? TenantDB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        if (! $currentSession || ! $currentTerm) {
            return response()->json(['message' => 'Current academic session/term is not set.'], 400);
        }

        $classId = (int) $data['class_id'];
        $subjectId = isset($data['subject_id']) ? (int) $data['subject_id'] : null;
        $date = $data['date'];

        // Verify teacher is assigned to this class and subject (if subject provided)
        if ($subjectId) {
            $isAssigned = TenantDB::table('teacher_class')
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
            $isFormTeacher = TenantDB::table('classes')
                ->where('id', $classId)
                ->where('form_teacher_id', $teacherId)
                ->exists();

            $isAssignedToClass = TenantDB::table('teacher_class')
                ->where('class_id', $classId)
                ->where('teacher_id', $teacherId)
                ->exists();

            if (! $isFormTeacher && ! $isAssignedToClass) {
                return response()->json(['message' => 'You can only mark attendance for classes you are assigned to.'], 403);
            }
        }

        $sessionId = null;
        DB::transaction(function () use ($tenantId, $request, $currentSession, $currentTerm, $data, $classId, $subjectId, $date, &$sessionId) {
            $existing = TenantDB::table('attendance_sessions')
                ->where('class_id', $classId)
                ->where('subject_id', $subjectId)
                ->where('academic_session_id', $currentSession->id)
                ->where('term_id', $currentTerm->id)
                ->where('date', $date)
                ->first();

            if ($existing) {
                $sessionId = $existing->id;
                TenantDB::table('attendance_sessions')->where('id', $sessionId)->update([
                    'week' => $data['week'] ?? $existing->week,
                    'updated_at' => now(),
                ]);
            } else {
                $sessionId = DB::table('attendance_sessions')->insertGetId([
                    'tenant_id' => $tenantId,
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
                    ['tenant_id' => $tenantId, 'attendance_session_id' => $sessionId, 'student_id' => (int) $r['student_id']],
                    ['status' => $r['status'], 'updated_at' => now(), 'created_at' => now(), 'tenant_id' => $tenantId]
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
                'tenant_id' => $tenantId,
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


