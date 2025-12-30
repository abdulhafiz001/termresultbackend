<?php

namespace App\Http\Controllers\Tenant\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Admin\GradingConfigsController;
use App\Support\TenantCache;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExamsController extends Controller
{
    public function index(Request $request)
    {
        $teacherId = $request->user()->id;

        $currentSession = DB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? DB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        if (! $currentSession || ! $currentTerm) {
            return response()->json(['message' => 'Current academic session/term is not set.'], 400);
        }

        $q = DB::table('exams as e')
            ->join('exam_question_submissions as s', 's.id', '=', 'e.submission_id')
            ->join('classes as c', 'c.id', '=', 'e.class_id')
            ->join('subjects as sub', 'sub.id', '=', 'e.subject_id')
            ->where('s.teacher_id', $teacherId)
            ->where('e.academic_session_id', $currentSession->id)
            ->where('e.term_id', $currentTerm->id)
            ->orderByDesc('e.id');

        $items = $q->get([
            'e.id',
            'e.code',
            'e.exam_type',
            'e.duration_minutes',
            'e.question_count',
            'e.marks_per_question',
            'e.status',
            'e.started_at',
            'e.ended_at',
            'e.answer_key',
            'c.name as class_name',
            'sub.name as subject_name',
            'sub.code as subject_code',
        ])->map(function ($e) {
            $key = $e->answer_key;
            if (is_string($key)) $key = json_decode($key, true) ?? null;
            return [
                'id' => $e->id,
                'code' => $e->code,
                'exam_type' => $e->exam_type,
                'duration_minutes' => (int) $e->duration_minutes,
                'question_count' => $e->question_count ? (int) $e->question_count : null,
                'marks_per_question' => $e->marks_per_question ? (int) $e->marks_per_question : null,
                'status' => $e->status,
                'started_at' => $e->started_at,
                'ended_at' => $e->ended_at,
                'has_answer_key' => $key ? true : false,
                'answer_key' => $key, // Include answer key in response
                'class_name' => $e->class_name,
                'subject_name' => $e->subject_name,
                'subject_code' => $e->subject_code,
            ];
        });

        return response()->json([
            'meta' => [
                'academic_session_id' => $currentSession->id,
                'term_id' => $currentTerm->id,
            ],
            'data' => $items,
        ]);
    }

    public function setAnswerKey(Request $request, int $examId)
    {
        $teacherId = $request->user()->id;
        $data = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'marks' => ['nullable', 'array'],
            'marks.*' => ['nullable', 'integer', 'min:1'],
        ]);

        $exam = DB::table('exams as e')
            ->join('exam_question_submissions as s', 's.id', '=', 'e.submission_id')
            ->where('e.id', $examId)
            ->where('s.teacher_id', $teacherId)
            ->select(['e.*'])
            ->first();

        if (! $exam) return response()->json(['message' => 'Exam not found.'], 404);
        if ($exam->exam_type !== 'objective') return response()->json(['message' => 'Answer key is only for objective exams.'], 422);

        $key = [];
        $marks = $data['marks'] ?? [];
        
        foreach ($data['answers'] as $k => $v) {
            $qno = is_numeric($k) ? (int) $k : (int) preg_replace('/\\D+/', '', (string) $k);
            $ans = strtoupper(trim((string) $v));
            if ($qno > 0 && in_array($ans, ['A', 'B', 'C', 'D', 'E'], true)) {
                // Store answer with mark if provided
                $mark = isset($marks[$k]) && is_numeric($marks[$k]) ? (int) $marks[$k] : null;
                if ($mark !== null && $mark > 0) {
                    $key[(string) $qno] = [
                        'answer' => $ans,
                        'mark' => $mark,
                    ];
                } else {
                    // Backward compatibility: if no mark, store just answer
                    $key[(string) $qno] = $ans;
                }
            }
        }
        if (count($key) === 0) {
            return response()->json(['message' => 'No valid answers provided. Use A-E.'], 422);
        }

        DB::table('exams')->where('id', $examId)->update([
            'answer_key' => json_encode($key),
            'updated_at' => now(),
        ]);

        // Auto-grade all submitted attempts missing objective_score.
        $attempts = DB::table('exam_attempts')
            ->where('exam_id', $examId)
            ->where('status', 'submitted')
            ->whereNull('objective_score')
            ->get();

        foreach ($attempts as $a) {
            $score = $this->computeObjectiveScore($a->id, $key, null);
            DB::table('exam_attempts')->where('id', $a->id)->update([
                'objective_score' => $score,
                'updated_at' => now(),
            ]);

            $studentClassId = (int) (DB::table('student_profiles')->where('user_id', $a->student_id)->value('current_class_id') ?? 0);
            $this->upsertStudentScoreExam((int) $a->student_id, (int) $exam->subject_id, (int) $exam->academic_session_id, (int) $exam->term_id, $score, $studentClassId);
        }

        return response()->json(['message' => 'Answer key saved and auto-marking applied where possible.']);
    }

    public function attempts(Request $request, int $examId)
    {
        $teacherId = $request->user()->id;

        $exam = DB::table('exams as e')
            ->join('exam_question_submissions as s', 's.id', '=', 'e.submission_id')
            ->where('e.id', $examId)
            ->where('s.teacher_id', $teacherId)
            ->select(['e.id', 'e.exam_type'])
            ->first();
        if (! $exam) return response()->json(['message' => 'Exam not found.'], 404);

        $rows = DB::table('exam_attempts as a')
            ->join('users as u', 'u.id', '=', 'a.student_id')
            ->leftJoin('student_profiles as sp', 'sp.user_id', '=', 'u.id')
            ->where('a.exam_id', $examId)
            ->orderByDesc('a.updated_at')
            ->get([
                'a.id',
                'a.student_id',
                'u.admission_number',
                'sp.first_name',
                'sp.last_name',
                'a.status',
                'a.started_at',
                'a.submitted_at',
                'a.objective_score',
                'a.total_score',
                'a.marked_at',
            ])
            ->map(fn ($r) => [
                'attempt_id' => $r->id,
                'student_id' => $r->student_id,
                'student_name' => trim(($r->last_name ?? '') . ' ' . ($r->first_name ?? '')),
                'admission_number' => $r->admission_number,
                'status' => $r->status,
                'started_at' => $r->started_at,
                'submitted_at' => $r->submitted_at,
                'objective_score' => $r->objective_score,
                'total_score' => $r->total_score,
                'marked_at' => $r->marked_at,
            ]);

        return response()->json(['data' => $rows]);
    }

    public function attemptDetail(Request $request, int $attemptId)
    {
        $teacherId = $request->user()->id;

        $attempt = DB::table('exam_attempts as a')
            ->join('exams as e', 'e.id', '=', 'a.exam_id')
            ->join('exam_question_submissions as s', 's.id', '=', 'e.submission_id')
            ->join('subjects as sub', 'sub.id', '=', 'e.subject_id')
            ->join('classes as c', 'c.id', '=', 'e.class_id')
            ->join('users as u', 'u.id', '=', 'a.student_id')
            ->leftJoin('student_profiles as sp', 'sp.user_id', '=', 'u.id')
            ->where('a.id', $attemptId)
            ->where('s.teacher_id', $teacherId)
            ->select([
                'a.*',
                'e.exam_type',
                'e.answer_key',
                'e.duration_minutes',
                'e.question_count',
                'e.subject_id',
                'e.academic_session_id',
                'e.term_id',
                'sub.name as subject_name',
                'c.name as class_name',
                'u.admission_number',
                'sp.first_name',
                'sp.last_name',
            ])
            ->first();

        if (! $attempt) return response()->json(['message' => 'Attempt not found.'], 404);

        $answers = DB::table('exam_attempt_answers')
            ->where('attempt_id', $attemptId)
            ->orderBy('question_number')
            ->get()
            ->map(fn ($a) => [
                'question_number' => $a->question_number,
                'objective_choice' => $a->objective_choice,
                'answer_text' => $a->answer_text,
                'mark' => $a->mark,
            ]);

        $key = $attempt->answer_key;
        if (is_string($key)) $key = json_decode($key, true) ?? null;

        return response()->json([
            'attempt' => [
                'id' => $attempt->id,
                'exam_id' => $attempt->exam_id,
                'exam_type' => $attempt->exam_type,
                'status' => $attempt->status,
                'started_at' => $attempt->started_at,
                'submitted_at' => $attempt->submitted_at,
                'objective_score' => $attempt->objective_score,
                'total_score' => $attempt->total_score,
                'student' => [
                    'id' => $attempt->student_id,
                    'name' => trim(($attempt->last_name ?? '') . ' ' . ($attempt->first_name ?? '')),
                    'admission_number' => $attempt->admission_number,
                    'class_name' => $attempt->class_name,
                ],
                'subject_name' => $attempt->subject_name,
                'question_count' => $attempt->question_count,
            ],
            'answer_key' => $key,
            'answers' => $answers,
        ]);
    }

    public function markAttempt(Request $request, int $attemptId)
    {
        $teacherId = $request->user()->id;
        $data = $request->validate([
            'marks' => ['required', 'array', 'min:1'],
            'marks.*.question_number' => ['required', 'integer', 'min:1'],
            'marks.*.mark' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $attempt = DB::table('exam_attempts as a')
            ->join('exams as e', 'e.id', '=', 'a.exam_id')
            ->join('exam_question_submissions as s', 's.id', '=', 'e.submission_id')
            ->where('a.id', $attemptId)
            ->where('s.teacher_id', $teacherId)
            ->select(['a.*', 'e.subject_id', 'e.academic_session_id', 'e.term_id'])
            ->first();
        if (! $attempt) return response()->json(['message' => 'Attempt not found.'], 404);
        if ($attempt->status !== 'submitted') return response()->json(['message' => 'Attempt must be submitted before marking.'], 422);

        $total = 0;
        DB::transaction(function () use ($attemptId, $data, &$total) {
            foreach ($data['marks'] as $m) {
                DB::table('exam_attempt_answers')->updateOrInsert(
                    ['attempt_id' => $attemptId, 'question_number' => (int) $m['question_number']],
                    ['mark' => (int) $m['mark'], 'updated_at' => now(), 'created_at' => now()]
                );
                $total += (int) $m['mark'];
            }
        });

        // For MVP we cap at 100.
        $total = min(100, $total);

        DB::table('exam_attempts')->where('id', $attemptId)->update([
            'total_score' => $total,
            'marked_by' => $teacherId,
            'marked_at' => now(),
            'updated_at' => now(),
        ]);

        $studentClassId = (int) (DB::table('student_profiles')->where('user_id', $attempt->student_id)->value('current_class_id') ?? 0);
        $this->upsertStudentScoreExam((int) $attempt->student_id, (int) $attempt->subject_id, (int) $attempt->academic_session_id, (int) $attempt->term_id, $total, $studentClassId);

        return response()->json(['message' => 'Marked.', 'total_score' => $total]);
    }

    public function answerSlipPdf(Request $request, int $attemptId)
    {
        $teacherId = $request->user()->id;

        $attempt = DB::table('exam_attempts as a')
            ->join('exams as e', 'e.id', '=', 'a.exam_id')
            ->join('exam_question_submissions as s', 's.id', '=', 'e.submission_id')
            ->join('subjects as sub', 'sub.id', '=', 'e.subject_id')
            ->join('classes as c', 'c.id', '=', 'e.class_id')
            ->join('users as u', 'u.id', '=', 'a.student_id')
            ->leftJoin('student_profiles as sp', 'sp.user_id', '=', 'u.id')
            ->where('a.id', $attemptId)
            ->where('s.teacher_id', $teacherId)
            ->select([
                'a.id as attempt_id',
                'a.status',
                'a.started_at',
                'a.submitted_at',
                'a.objective_score',
                'a.total_score',
                'e.exam_type',
                'e.code',
                'sub.name as subject_name',
                'c.name as class_name',
                'u.admission_number',
                'sp.first_name',
                'sp.last_name',
            ])
            ->first();

        if (! $attempt) return response()->json(['message' => 'Attempt not found.'], 404);

        $answers = DB::table('exam_attempt_answers')
            ->where('attempt_id', $attemptId)
            ->orderBy('question_number')
            ->get();

        $studentName = trim(($attempt->last_name ?? '') . ' ' . ($attempt->first_name ?? ''));
        if (empty($studentName)) {
            $studentName = 'student-' . $attempt->admission_number;
        }
        
        $classSlug = Str::slug($attempt->class_name ?: 'class');
        $subjectSlug = Str::slug($attempt->subject_name ?: 'subject');
        $studentSlug = Str::slug($studentName);
        
        $filename = $studentSlug . '-' . $classSlug . '-' . $subjectSlug . '-exam-slip.pdf';

        $pdf = Pdf::loadView('pdf.exam-answer-slip', [
            'attempt' => $attempt,
            'studentName' => $studentName,
            'answers' => $answers,
        ]);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function computeObjectiveScore(int $attemptId, array $key, ?int $marksPerQuestion): int
    {
        $answers = DB::table('exam_attempt_answers')->where('attempt_id', $attemptId)->get()->keyBy('question_number');
        $score = 0;
        $total = 0;
        
        foreach ($key as $qno => $item) {
            $total++;
            $given = $answers->get((int) $qno);
            
            // Handle new format: { answer: 'A', mark: 5 } or old format: 'A'
            $correctAnswer = is_array($item) ? ($item['answer'] ?? null) : $item;
            $mark = is_array($item) && isset($item['mark']) ? (int) $item['mark'] : null;
            
            if ($given && $correctAnswer && strtoupper((string) $given->objective_choice) === strtoupper((string) $correctAnswer)) {
                if ($mark !== null && $mark > 0) {
                    // Use per-question mark if available
                    $score += $mark;
                } else {
                    // Fallback to marks_per_question if provided, otherwise default to 1
                    $mpq = $marksPerQuestion !== null ? max(1, (int) $marksPerQuestion) : 1;
                    $score += $mpq;
                }
            }
        }
        
        // Cap at 100 to fit results total model
        return min(100, $score);
    }

    private function upsertStudentScoreExam(int $studentId, int $subjectId, int $sessionId, int $termId, int $examScore, int $studentClassId): void
    {
        $existing = DB::table('student_scores')->where([
            'student_id' => $studentId,
            'subject_id' => $subjectId,
            'academic_session_id' => $sessionId,
            'term_id' => $termId,
        ])->first();

        $ca1 = $existing->ca1 ?? null;
        $ca2 = $existing->ca2 ?? null;
        $total = (int) (($ca1 ?? 0) + ($ca2 ?? 0) + ($examScore ?? 0));
        $grade = GradingConfigsController::gradeForClassTotal($studentClassId, $total) ?? $this->fallbackGrade($total);

        DB::table('student_scores')->updateOrInsert(
            [
                'student_id' => $studentId,
                'subject_id' => $subjectId,
                'academic_session_id' => $sessionId,
                'term_id' => $termId,
            ],
            [
                'ca1' => $ca1,
                'ca2' => $ca2,
                'exam' => $examScore,
                'total' => $total,
                'grade' => $grade,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $school = app('tenant.school');
        TenantCache::forgetStudentCaches($school, $studentId, $sessionId, $termId);
    }

    private function fallbackGrade(int $total): string
    {
        if ($total >= 75) return 'A';
        if ($total >= 65) return 'B';
        if ($total >= 50) return 'C';
        if ($total >= 40) return 'D';
        return 'F';
    }
}


