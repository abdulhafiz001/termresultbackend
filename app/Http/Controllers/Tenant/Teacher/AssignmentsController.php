<?php

namespace App\Http\Controllers\Tenant\Teacher;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AssignmentsController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = TenantContext::id();
        $teacherId = $request->user()->id;

        // Check if assignments table exists
        if (!\Illuminate\Support\Facades\Schema::hasTable('assignments')) {
            return response()->json(['data' => [], 'message' => 'Assignments table does not exist. Please run migrations.']);
        }

        $currentSession = TenantDB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? TenantDB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        if (!$currentSession || !$currentTerm) {
            return response()->json(['data' => [], 'message' => 'Current academic session/term is not set.']);
        }

        try {
            $assignments = DB::table('assignments as a')
                ->where('a.tenant_id', $tenantId)
                ->join('classes as c', function ($j) {
                    $j->on('c.id', '=', 'a.class_id')
                        ->on('c.tenant_id', '=', 'a.tenant_id');
                })
                ->join('subjects as s', function ($j) {
                    $j->on('s.id', '=', 'a.subject_id')
                        ->on('s.tenant_id', '=', 'a.tenant_id');
                })
                ->where('a.teacher_id', $teacherId)
                ->where('a.academic_session_id', $currentSession->id)
                ->where('a.term_id', $currentTerm->id)
                ->orderByDesc('a.id')
                ->select([
                    'a.id',
                    'a.assignment_number',
                    'a.class_id',
                    'a.subject_id',
                    'a.question',
                    'a.image_path',
                    'a.created_at',
                    'c.name as class_name',
                    's.name as subject_name',
                    's.code as subject_code',
                ])
                ->get();

            return response()->json(['data' => $assignments]);
        } catch (\Exception $e) {
            \Log::error('Failed to load assignments: ' . $e->getMessage());
            return response()->json(['data' => [], 'message' => 'Failed to load assignments. Please ensure migrations have been run.'], 500);
        }
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();
        $teacherId = $request->user()->id;
        $data = $request->validate([
            'class_id' => ['required', Rule::exists('classes', 'id')->where('tenant_id', $tenantId)],
            'subject_id' => ['required', Rule::exists('subjects', 'id')->where('tenant_id', $tenantId)],
            'question' => ['required', 'string', 'min:1'],
            'has_image' => ['sometimes', 'boolean'],
            'image' => ['required_if:has_image,true', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'], // 2MB max
        ]);

        // Verify teacher teaches this class and subject
        $teachesClass = TenantDB::table('teacher_class')
            ->where('teacher_id', $teacherId)
            ->where('class_id', $data['class_id'])
            ->exists();

        $teachesSubject = TenantDB::table('teacher_subject')
            ->where('teacher_id', $teacherId)
            ->where('subject_id', $data['subject_id'])
            ->exists();

        if (!$teachesClass || !$teachesSubject) {
            return response()->json(['message' => 'You are not assigned to teach this class/subject.'], 403);
        }

        $currentSession = TenantDB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? TenantDB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        if (!$currentSession || !$currentTerm) {
            return response()->json(['message' => 'Current academic session/term is not set.'], 400);
        }

        // Generate assignment number
        $assignmentNumber = 'ASS-' . strtoupper(Str::random(8));

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store("tenants/{$tenantId}/assignments", 'public');
        }

        // Some schemas still require a non-null `title`. Use the first part of the question as a safe title.
        $title = Str::limit(trim((string) $data['question']), 120, '…');

        $assignmentId = DB::table('assignments')->insertGetId([
            'tenant_id' => $tenantId,
            'assignment_number' => $assignmentNumber,
            'teacher_id' => $teacherId,
            'class_id' => $data['class_id'],
            'subject_id' => $data['subject_id'],
            'academic_session_id' => $currentSession->id,
            'term_id' => $currentTerm->id,
            'title' => $title,
            'question' => $data['question'],
            'image_path' => $imagePath,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
            ->select([
                'a.id',
                'a.assignment_number',
                'a.class_id',
                'a.subject_id',
                'a.question',
                'a.image_path',
                'a.created_at',
                'c.name as class_name',
                's.name as subject_name',
                's.code as subject_code',
            ])
            ->first();

        return response()->json(['data' => $assignment, 'message' => 'Assignment posted successfully.'], 201);
    }

    public function getSubmissions(Request $request)
    {
        $tenantId = TenantContext::id();
        $teacherId = $request->user()->id;
        $filters = $request->validate([
            'class_id' => ['sometimes', Rule::exists('classes', 'id')->where('tenant_id', $tenantId)],
            'subject_id' => ['sometimes', Rule::exists('subjects', 'id')->where('tenant_id', $tenantId)],
            'assignment_id' => ['sometimes', Rule::exists('assignments', 'id')->where('tenant_id', $tenantId)],
            'academic_session_id' => ['sometimes', Rule::exists('academic_sessions', 'id')->where('tenant_id', $tenantId)],
            'term_id' => ['sometimes', Rule::exists('terms', 'id')->where('tenant_id', $tenantId)],
        ]);

        $currentSession = TenantDB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? TenantDB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        $sessionId = $filters['academic_session_id'] ?? $currentSession->id ?? null;
        $termId = $filters['term_id'] ?? $currentTerm->id ?? null;

        if (!$sessionId || !$termId) {
            return response()->json(['message' => 'Academic session/term is required.'], 400);
        }

        $query = DB::table('assignment_submissions as sub')
            ->where('sub.tenant_id', $tenantId)
            ->join('assignments as a', function ($j) {
                $j->on('a.id', '=', 'sub.assignment_id')
                    ->on('a.tenant_id', '=', 'sub.tenant_id');
            })
            ->join('users as u', function ($j) {
                $j->on('u.id', '=', 'sub.student_id')
                    ->on('u.tenant_id', '=', 'sub.tenant_id');
            })
            ->join('classes as c', function ($j) {
                $j->on('c.id', '=', 'a.class_id')
                    ->on('c.tenant_id', '=', 'sub.tenant_id');
            })
            ->join('subjects as s', function ($j) {
                $j->on('s.id', '=', 'a.subject_id')
                    ->on('s.tenant_id', '=', 'sub.tenant_id');
            })
            ->where('a.teacher_id', $teacherId)
            ->where('a.academic_session_id', $sessionId)
            ->where('a.term_id', $termId);

        if (isset($filters['class_id'])) {
            $query->where('a.class_id', $filters['class_id']);
        }

        if (isset($filters['subject_id'])) {
            $query->where('a.subject_id', $filters['subject_id']);
        }

        if (isset($filters['assignment_id'])) {
            $query->where('a.id', $filters['assignment_id']);
        }

        $submissions = $query->orderByDesc('sub.id')
            ->select([
                'sub.id',
                'sub.assignment_id',
                'sub.student_id',
                'sub.answer',
                'sub.score',
                'sub.submitted_at',
                'sub.marked_at',
                'a.assignment_number',
                'a.question as assignment_question',
                'a.image_path as assignment_image',
                'u.name as student_name',
                'u.admission_number',
                'c.name as class_name',
                's.name as subject_name',
            ])
            ->get();

        return response()->json(['data' => $submissions]);
    }

    public function markSubmission(Request $request, int $submissionId)
    {
        $tenantId = TenantContext::id();
        $teacherId = $request->user()->id;
        $data = $request->validate([
            'score' => ['required', 'numeric', 'min:0'],
        ]);

        $submission = DB::table('assignment_submissions as sub')
            ->where('sub.tenant_id', $tenantId)
            ->join('assignments as a', function ($j) {
                $j->on('a.id', '=', 'sub.assignment_id')
                    ->on('a.tenant_id', '=', 'sub.tenant_id');
            })
            ->where('sub.id', $submissionId)
            ->where('a.teacher_id', $teacherId)
            ->select(['sub.*'])
            ->first();

        if (!$submission) {
            return response()->json(['message' => 'Submission not found.'], 404);
        }

        DB::table('assignment_submissions')
            ->where('tenant_id', $tenantId)
            ->where('id', $submissionId)
            ->update([
                'score' => $data['score'],
                'marked_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['message' => 'Assignment marked successfully.']);
    }
}

