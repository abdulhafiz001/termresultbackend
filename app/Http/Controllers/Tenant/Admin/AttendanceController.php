<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $classId = $request->query('class_id');
        $studentId = $request->query('student_id');
        $date = $request->query('date');
        $sessionId = $request->query('academic_session_id');
        $termId = $request->query('term_id');
        $subjectId = $request->query('subject_id'); // optional (for subject attendance)

        $currentSession = DB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? DB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        $sessionId = is_numeric($sessionId) ? (int) $sessionId : ($currentSession->id ?? null);
        $termId = is_numeric($termId) ? (int) $termId : ($currentTerm->id ?? null);
        $classId = is_numeric($classId) ? (int) $classId : null;
        $studentId = is_numeric($studentId) ? (int) $studentId : null;
        $subjectId = is_numeric($subjectId) ? (int) $subjectId : null;

        if (is_string($date) && $date !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            // Ignore bad dates like "undefined"
            $date = null;
        }

        // Get weekly stats
        $weeklyStats = [];
        if ($sessionId && $termId) {
            $weeklyStats = DB::table('attendance_sessions')
                ->where('academic_session_id', $sessionId)
                ->where('term_id', $termId)
                ->when($subjectId !== null, function ($q) use ($subjectId) {
                    $q->where('subject_id', $subjectId);
                })
                ->when($classId, function ($q) use ($classId) {
                    $q->where('class_id', $classId);
                })
                ->select(DB::raw('week, COUNT(DISTINCT date) as days_recorded'))
                ->groupBy('week')
                ->orderBy('week')
                ->get();
        }

        // Get daily attendance
        $dailyAttendance = [];
        if ($date && $classId) {
            // Prefer class attendance (subject_id NULL) unless subject_id is explicitly provided.
            $sessionQuery = DB::table('attendance_sessions')
                ->where('class_id', $classId)
                ->where('academic_session_id', $sessionId)
                ->where('term_id', $termId)
                ->where('date', $date);

            if ($subjectId !== null) {
                $sessionQuery->where('subject_id', $subjectId);
            } else {
                $sessionQuery->orderByRaw('CASE WHEN subject_id IS NULL THEN 0 ELSE 1 END'); // prefer null first
            }

            $sessions = $sessionQuery->get();

            if ($sessions->count() > 0) {
                // If multiple sessions exist for the same date (subject attendance + class attendance),
                // return a combined list and include subject info for clarity.
                $sessionIds = $sessions->pluck('id')->values()->all();

                $dailyAttendance = DB::table('attendance_records')
                    ->join('attendance_sessions', 'attendance_records.attendance_session_id', '=', 'attendance_sessions.id')
                    ->join('users', 'attendance_records.student_id', '=', 'users.id')
                    ->leftJoin('student_profiles as sp', 'sp.user_id', '=', 'users.id')
                    ->leftJoin('subjects', 'attendance_sessions.subject_id', '=', 'subjects.id')
                    ->whereIn('attendance_records.attendance_session_id', $sessionIds)
                    ->select([
                        'attendance_records.id',
                        'attendance_records.student_id',
                        DB::raw("COALESCE(
                            CONCAT(TRIM(CONCAT(COALESCE(sp.first_name, ''), ' ', COALESCE(sp.last_name, ''))), 
                            CASE WHEN sp.middle_name IS NOT NULL AND sp.middle_name != '' THEN CONCAT(' ', sp.middle_name) ELSE '' END),
                            users.name
                        ) as student_name"),
                        'users.admission_number',
                        'attendance_records.status',
                        'attendance_sessions.subject_id',
                        'subjects.name as subject_name',
                    ])
                    ->orderBy('student_name')
                    ->get();
            }
        }

        // Get student attendance stats
        $studentStats = null;
        if ($studentId && $sessionId && $termId) {
            // Get student's class_id from their profile
            $studentClassId = (int) (DB::table('student_profiles')->where('user_id', $studentId)->value('current_class_id') ?? 0);
            
            // Use provided classId or student's class
            $filterClassId = $classId ? (int) $classId : $studentClassId;
            
            if ($filterClassId) {
                $totalDays = DB::table('attendance_sessions')
                    ->where('academic_session_id', $sessionId)
                    ->where('term_id', $termId)
                    ->whereNull('subject_id')
                    ->where('class_id', $filterClassId)
                    ->distinct('date')
                    ->count('date');

                $presentDays = DB::table('attendance_records')
                    ->join('attendance_sessions', 'attendance_records.attendance_session_id', '=', 'attendance_sessions.id')
                    ->where('attendance_records.student_id', $studentId)
                    ->where('attendance_sessions.academic_session_id', $sessionId)
                    ->where('attendance_sessions.term_id', $termId)
                    ->whereNull('attendance_sessions.subject_id')
                    ->where('attendance_sessions.class_id', $filterClassId)
                    ->where('attendance_records.status', 'present')
                    ->count();

                $absentDays = DB::table('attendance_records')
                    ->join('attendance_sessions', 'attendance_records.attendance_session_id', '=', 'attendance_sessions.id')
                    ->where('attendance_records.student_id', $studentId)
                    ->where('attendance_sessions.academic_session_id', $sessionId)
                    ->where('attendance_sessions.term_id', $termId)
                    ->whereNull('attendance_sessions.subject_id')
                    ->where('attendance_sessions.class_id', $filterClassId)
                    ->where('attendance_records.status', 'absent')
                    ->count();

                $lateDays = DB::table('attendance_records')
                    ->join('attendance_sessions', 'attendance_records.attendance_session_id', '=', 'attendance_sessions.id')
                    ->where('attendance_records.student_id', $studentId)
                    ->where('attendance_sessions.academic_session_id', $sessionId)
                    ->where('attendance_sessions.term_id', $termId)
                    ->whereNull('attendance_sessions.subject_id')
                    ->where('attendance_sessions.class_id', $filterClassId)
                    ->where('attendance_records.status', 'late')
                    ->count();

                $excusedDays = DB::table('attendance_records')
                    ->join('attendance_sessions', 'attendance_records.attendance_session_id', '=', 'attendance_sessions.id')
                    ->where('attendance_records.student_id', $studentId)
                    ->where('attendance_sessions.academic_session_id', $sessionId)
                    ->where('attendance_sessions.term_id', $termId)
                    ->whereNull('attendance_sessions.subject_id')
                    ->where('attendance_sessions.class_id', $filterClassId)
                    ->where('attendance_records.status', 'excused')
                    ->count();

                $studentStats = [
                    'total_days' => $totalDays,
                    'present_days' => $presentDays,
                    'absent_days' => $absentDays,
                    'late_days' => $lateDays,
                    'excused_days' => $excusedDays,
                    'attendance_rate' => $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 1) : 0,
                ];
            }
        }

        return response()->json([
            'data' => [
                'weekly_stats' => $weeklyStats,
                'daily_attendance' => $dailyAttendance,
                'student_stats' => $studentStats,
                'current_session' => $currentSession,
                'current_term' => $currentTerm,
            ],
        ]);
    }
}

