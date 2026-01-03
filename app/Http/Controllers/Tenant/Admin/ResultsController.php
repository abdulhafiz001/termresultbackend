<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Admin\GradingConfigsController;
use App\Support\TenantDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResultsController extends Controller
{
    public function index(Request $request)
    {
        // This is handled by the frontend - just return empty for now
        return response()->json(['data' => []]);
    }

    public function showStudentResults(Request $request, int $studentId)
    {
        $sessionId = (int) $request->query('academic_session_id');
        $termId = (int) $request->query('term_id');

        $currentSession = TenantDB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? TenantDB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        $sessionId = $sessionId ?: ($currentSession->id ?? 0);
        $termId = $termId ?: ($currentTerm->id ?? 0);

        if (! $sessionId || ! $termId) {
            return response()->json(['message' => 'Academic session/term not set.'], 400);
        }

        // Get student info
        $student = TenantDB::table('users')
            ->leftJoin('student_profiles', function ($j) {
                $j->on('users.id', '=', 'student_profiles.user_id')
                    ->on('users.tenant_id', '=', 'student_profiles.tenant_id');
            })
            ->leftJoin('classes', function ($j) {
                $j->on('student_profiles.current_class_id', '=', 'classes.id')
                    ->on('student_profiles.tenant_id', '=', 'classes.tenant_id');
            })
            ->where('users.id', $studentId)
            ->where('users.role', 'student')
            ->select([
                'users.id',
                'users.name',
                'users.admission_number',
                'users.email',
                'student_profiles.first_name',
                'student_profiles.last_name',
                'student_profiles.middle_name',
                'classes.id as class_id',
                'classes.name as class_name',
            ])
            ->first();

        if (! $student) {
            return response()->json(['message' => 'Student not found.'], 404);
        }

        // Get results
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
                'subjects.code as subject_code',
                'student_scores.ca1',
                'student_scores.ca2',
                'student_scores.exam',
                'student_scores.total',
                'student_scores.grade',
                'student_scores.remark',
            ])
            ->orderBy('subjects.name')
            ->get();

        $classId = (int) ($student->class_id ?? 0);
        $rows = $rows->map(function ($r) use ($classId) {
            $totalInt = is_numeric($r->total) ? (int) round((float) $r->total) : null;
            $dynamicGrade = ($classId && $totalInt !== null) ? GradingConfigsController::gradeForClassTotal($classId, $totalInt) : null;
            return [
                'subject_id' => (int) $r->subject_id,
                'subject_name' => $r->subject_name,
                'subject_code' => $r->subject_code,
                'ca1' => $r->ca1,
                'ca2' => $r->ca2,
                'exam' => $r->exam,
                'total' => $r->total,
                'grade' => $dynamicGrade ?: $r->grade,
                'remark' => $r->remark,
            ];
        });

        // Calculate totals
        $totalScore = $rows->sum('total');
        $averageScore = $rows->count() > 0 ? round($totalScore / $rows->count(), 1) : 0;

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

        return response()->json([
            'student' => $student,
            'results' => $rows,
            'summary' => [
                'total_subjects' => $rows->count(),
                'total_score' => $totalScore,
                'average_score' => $averageScore,
            ],
            'meta' => [
                'academic_session_id' => $sessionId,
                'term_id' => $termId,
                'session_name' => $currentSession->name ?? '',
                'term_name' => $currentTerm->name ?? '',
                'promotion' => $promotion ? [
                    'status' => $promotion->status,
                    'to_class_id' => $promotion->to_class_id,
                    'to_class_name' => $promotion->to_class_name,
                ] : null,
            ],
        ]);
    }
}

