<?php

namespace App\Http\Controllers\Tenant\Student;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AssignmentsController extends Controller
{
    public function index(Request $request)
    {
        $studentId = $request->user()->id;
        $tenantId = TenantContext::id();

        // Ensure assignments tables exist (avoid hard 500s if migrations weren't run)
        if (!Schema::hasTable('assignments') || !Schema::hasTable('assignment_submissions')) {
            return response()->json([
                'data' => [],
                'message' => 'Assignments feature is not available yet. Please ensure database migrations have been run.',
            ]);
        }

        // Student class is stored on student_profiles.current_class_id (not users.class_id)
        $profile = TenantDB::table('student_profiles')
            ->where('user_id', $studentId)
            ->select(['current_class_id'])
            ->first();

        $classId = (int) ($profile->current_class_id ?? 0);
        if ($classId <= 0) {
            return response()->json(['message' => 'Student class not found.'], 404);
        }

        $studentSubjects = DB::table('student_subject')
            ->where('tenant_id', $tenantId)
            ->where('student_id', $studentId)
            ->pluck('subject_id')
            ->toArray();

        // If subjects weren't explicitly assigned (common after bulk import),
        // fall back to class subjects so students can still see assignments.
        if (empty($studentSubjects) && Schema::hasTable('class_subject')) {
            $studentSubjects = DB::table('class_subject')
                ->where('tenant_id', $tenantId)
                ->where('class_id', $classId)
                ->pluck('subject_id')
                ->toArray();
        }
        // If still empty, don't hard-block: show all assignments for the class (subject filtering disabled).
        $applySubjectFilter = ! empty($studentSubjects);

        $currentSession = TenantDB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? TenantDB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        if (!$currentSession || !$currentTerm) {
            return response()->json(['message' => 'Current academic session/term is not set.'], 400);
        }

        $query = DB::table('assignments as a')
            ->where('a.tenant_id', $tenantId)
            ->leftJoin('assignment_submissions as sub', function ($join) use ($studentId) {
                $join->on('sub.assignment_id', '=', 'a.id')
                    ->where('sub.student_id', '=', $studentId);
            })
            ->join('classes as c', function ($j) {
                $j->on('c.id', '=', 'a.class_id')
                    ->on('c.tenant_id', '=', 'a.tenant_id');
            })
            ->join('subjects as s', function ($j) {
                $j->on('s.id', '=', 'a.subject_id')
                    ->on('s.tenant_id', '=', 'a.tenant_id');
            })
            ->where('a.class_id', $classId)
            ->where('a.academic_session_id', (int) $currentSession->id)
            ->where('a.term_id', $currentTerm->id)
            ->orderByDesc('a.id')
            ->select([
                'a.id',
                'a.assignment_number',
                'a.question',
                'a.image_path',
                'a.created_at',
                'c.name as class_name',
                's.name as subject_name',
                's.code as subject_code',
                'sub.id as submission_id',
                'sub.score',
                'sub.submitted_at',
                'sub.marked_at',
            ]);

        if ($applySubjectFilter) {
            $query->whereIn('a.subject_id', $studentSubjects);
        }

        $assignments = $query->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'assignment_number' => $item->assignment_number,
                'question' => $item->question,
                'image_path' => $item->image_path,
                'created_at' => $item->created_at,
                'class_name' => $item->class_name,
                'subject_name' => $item->subject_name,
                'subject_code' => $item->subject_code,
                'is_submitted' => !is_null($item->submission_id),
                'is_marked' => !is_null($item->marked_at),
                'score' => $item->score,
                'submitted_at' => $item->submitted_at,
            ];
        });

        return response()->json(['data' => $assignments]);
    }

    public function show(Request $request, int $assignmentId)
    {
        $studentId = $request->user()->id;
        $tenantId = TenantContext::id();

        // Student class is stored on student_profiles.current_class_id (not users.class_id)
        $profile = TenantDB::table('student_profiles')
            ->where('user_id', $studentId)
            ->select(['current_class_id'])
            ->first();

        $classId = (int) ($profile->current_class_id ?? 0);
        if ($classId <= 0) {
            return response()->json(['message' => 'Student class not found.'], 404);
        }

        $assignment = DB::table('assignments as a')
            ->where('a.tenant_id', $tenantId)
            ->join('classes as c', function ($j) {
                $j->on('c.id', '=', 'a.class_id')
                    ->on('c.tenant_id', '=', 'a.tenant_id');
            })
            ->join('subjects as s', function ($j) {
                $j->on('s.id', '=', 'a.subject_id')
                    ->on('s.tenant_id', '=', 'a.tenant_id');
            })
            ->where('a.id', $assignmentId)
            ->where('a.class_id', $classId)
            ->select([
                'a.id',
                'a.assignment_number',
                'a.subject_id',
                'a.question',
                'a.image_path',
                'a.created_at',
                'c.name as class_name',
                's.name as subject_name',
                's.code as subject_code',
            ])
            ->first();

        if (!$assignment) {
            return response()->json(['message' => 'Assignment not found.'], 404);
        }

        // Check if student offers this subject
        $offersSubject = DB::table('student_subject')
            ->where('tenant_id', $tenantId)
            ->where('student_id', $studentId)
            ->where('subject_id', $assignment->subject_id)
            ->exists();

        if (!$offersSubject) {
            return response()->json(['message' => 'You do not offer this subject.'], 403);
        }

        // Get submission if exists
        $submission = DB::table('assignment_submissions')
            ->where('tenant_id', $tenantId)
            ->where('assignment_id', $assignmentId)
            ->where('student_id', $studentId)
            ->first();

        return response()->json([
            'data' => [
                'assignment' => $assignment,
                'submission' => $submission,
            ],
        ]);
    }

    public function submit(Request $request, int $assignmentId)
    {
        $studentId = $request->user()->id;
        $tenantId = TenantContext::id();
        $data = $request->validate([
            'answer' => ['required', 'string', 'min:1'],
        ]);

        // Student class is stored on student_profiles.current_class_id (not users.class_id)
        $profile = TenantDB::table('student_profiles')
            ->where('user_id', $studentId)
            ->select(['current_class_id'])
            ->first();

        $classId = (int) ($profile->current_class_id ?? 0);
        if ($classId <= 0) {
            return response()->json(['message' => 'Student class not found.'], 404);
        }

        $assignment = DB::table('assignments')
            ->where('tenant_id', $tenantId)
            ->where('id', $assignmentId)
            ->where('class_id', $classId)
            ->first();

        if (!$assignment) {
            return response()->json(['message' => 'Assignment not found.'], 404);
        }

        // Check if student offers this subject
        $offersSubject = DB::table('student_subject')
            ->where('tenant_id', $tenantId)
            ->where('student_id', $studentId)
            ->where('subject_id', $assignment->subject_id)
            ->exists();

        if (!$offersSubject) {
            return response()->json(['message' => 'You do not offer this subject.'], 403);
        }

        // Check if already submitted
        $existing = DB::table('assignment_submissions')
            ->where('tenant_id', $tenantId)
            ->where('assignment_id', $assignmentId)
            ->where('student_id', $studentId)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Assignment already submitted.'], 422);
        }

        DB::table('assignment_submissions')->insert([
            'tenant_id' => $tenantId,
            'assignment_id' => $assignmentId,
            'student_id' => $studentId,
            'answer' => $data['answer'],
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Assignment submitted successfully.'], 201);
    }
}

