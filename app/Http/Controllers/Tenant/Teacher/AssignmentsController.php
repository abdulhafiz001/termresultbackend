<?php

namespace App\Http\Controllers\Tenant\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AssignmentsController extends Controller
{
    public function index(Request $request)
    {
        $teacherId = $request->user()->id;

        // Check if assignments table exists
        if (!\Illuminate\Support\Facades\Schema::hasTable('assignments')) {
            return response()->json(['data' => [], 'message' => 'Assignments table does not exist. Please run migrations.']);
        }

        $currentSession = DB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? DB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        if (!$currentSession || !$currentTerm) {
            return response()->json(['data' => [], 'message' => 'Current academic session/term is not set.']);
        }

        try {
            $assignments = DB::table('assignments as a')
                ->join('classes as c', 'c.id', '=', 'a.class_id')
                ->join('subjects as s', 's.id', '=', 'a.subject_id')
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
        $teacherId = $request->user()->id;
        $data = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'question' => ['required', 'string', 'min:1'],
            'has_image' => ['sometimes', 'boolean'],
            'image' => ['required_if:has_image,true', 'image', 'max:5120'], // 5MB max
        ]);

        // Verify teacher teaches this class and subject
        $teachesClass = DB::table('teacher_class')
            ->where('teacher_id', $teacherId)
            ->where('class_id', $data['class_id'])
            ->exists();

        $teachesSubject = DB::table('teacher_subject')
            ->where('teacher_id', $teacherId)
            ->where('subject_id', $data['subject_id'])
            ->exists();

        if (!$teachesClass || !$teachesSubject) {
            return response()->json(['message' => 'You are not assigned to teach this class/subject.'], 403);
        }

        $currentSession = DB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? DB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        if (!$currentSession || !$currentTerm) {
            return response()->json(['message' => 'Current academic session/term is not set.'], 400);
        }

        // Generate assignment number
        $assignmentNumber = 'ASS-' . strtoupper(Str::random(8));

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('assignments', 'public');
        }

        $assignmentId = DB::table('assignments')->insertGetId([
            'assignment_number' => $assignmentNumber,
            'teacher_id' => $teacherId,
            'class_id' => $data['class_id'],
            'subject_id' => $data['subject_id'],
            'academic_session_id' => $currentSession->id,
            'term_id' => $currentTerm->id,
            'question' => $data['question'],
            'image_path' => $imagePath,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $assignment = DB::table('assignments as a')
            ->join('classes as c', 'c.id', '=', 'a.class_id')
            ->join('subjects as s', 's.id', '=', 'a.subject_id')
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
        $teacherId = $request->user()->id;
        $filters = $request->validate([
            'class_id' => ['sometimes', 'exists:classes,id'],
            'subject_id' => ['sometimes', 'exists:subjects,id'],
            'assignment_id' => ['sometimes', 'exists:assignments,id'],
            'academic_session_id' => ['sometimes', 'exists:academic_sessions,id'],
            'term_id' => ['sometimes', 'exists:terms,id'],
        ]);

        $currentSession = DB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? DB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        $sessionId = $filters['academic_session_id'] ?? $currentSession->id ?? null;
        $termId = $filters['term_id'] ?? $currentTerm->id ?? null;

        if (!$sessionId || !$termId) {
            return response()->json(['message' => 'Academic session/term is required.'], 400);
        }

        $query = DB::table('assignment_submissions as sub')
            ->join('assignments as a', 'a.id', '=', 'sub.assignment_id')
            ->join('users as u', 'u.id', '=', 'sub.student_id')
            ->join('classes as c', 'c.id', '=', 'a.class_id')
            ->join('subjects as s', 's.id', '=', 'a.subject_id')
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
        $teacherId = $request->user()->id;
        $data = $request->validate([
            'score' => ['required', 'numeric', 'min:0'],
        ]);

        $submission = DB::table('assignment_submissions as sub')
            ->join('assignments as a', 'a.id', '=', 'sub.assignment_id')
            ->where('sub.id', $submissionId)
            ->where('a.teacher_id', $teacherId)
            ->select(['sub.*'])
            ->first();

        if (!$submission) {
            return response()->json(['message' => 'Submission not found.'], 404);
        }

        DB::table('assignment_submissions')
            ->where('id', $submissionId)
            ->update([
                'score' => $data['score'],
                'marked_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['message' => 'Assignment marked successfully.']);
    }
}

