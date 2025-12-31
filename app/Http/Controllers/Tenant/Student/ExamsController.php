<?php

namespace App\Http\Controllers\Tenant\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Admin\GradingConfigsController;
use App\Support\TenantCache;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ExamsController extends Controller
{
    public function answerSlips(Request $request)
    {
        $tenantId = TenantContext::id();
        $student = $request->user();

        $currentSession = TenantDB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? TenantDB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        if (! $currentSession || ! $currentTerm) {
            return response()->json(['data' => []]);
        }

        $rows = DB::table('exam_attempts as a')
            ->where('a.tenant_id', $tenantId)
            ->where('a.student_id', $student->id)
            ->where('a.status', 'submitted')
            ->join('exams as e', function ($j) {
                $j->on('e.id', '=', 'a.exam_id')
                    ->on('e.tenant_id', '=', 'a.tenant_id');
            })
            ->join('subjects as sub', function ($j) {
                $j->on('sub.id', '=', 'e.subject_id')
                    ->on('sub.tenant_id', '=', 'e.tenant_id');
            })
            ->join('classes as c', function ($j) {
                $j->on('c.id', '=', 'e.class_id')
                    ->on('c.tenant_id', '=', 'e.tenant_id');
            })
            ->where('e.academic_session_id', $currentSession->id)
            ->where('e.term_id', $currentTerm->id)
            ->whereNotNull('e.answer_slip_released_at')
            ->orderByDesc('e.answer_slip_released_at')
            ->get([
                'a.id as attempt_id',
                'a.objective_score',
                'a.total_score',
                'e.id as exam_id',
                'e.exam_type',
                'e.code',
                'e.answer_slip_released_at',
                'sub.name as subject_name',
                'c.name as class_name',
            ])
            ->map(fn ($r) => [
                'attempt_id' => (int) $r->attempt_id,
                'exam' => [
                    'id' => (int) $r->exam_id,
                    'code' => $r->code,
                    'exam_type' => $r->exam_type,
                    'class_name' => $r->class_name,
                    'subject_name' => $r->subject_name,
                    'answer_slip_released_at' => $r->answer_slip_released_at,
                ],
                'score' => $r->exam_type === 'objective' ? $r->objective_score : $r->total_score,
                'download_url' => url("/api/tenant/student/attempts/{$r->attempt_id}/answer-slip"),
            ]);

        return response()->json(['data' => $rows]);
    }

    public function answerSlipPdf(Request $request, int $attemptId)
    {
        $tenantId = TenantContext::id();
        $student = $request->user();

        $attempt = DB::table('exam_attempts as a')
            ->where('a.tenant_id', $tenantId)
            ->where('a.id', $attemptId)
            ->where('a.student_id', $student->id)
            ->join('exams as e', function ($j) {
                $j->on('e.id', '=', 'a.exam_id')
                    ->on('e.tenant_id', '=', 'a.tenant_id');
            })
            ->join('subjects as sub', function ($j) {
                $j->on('sub.id', '=', 'e.subject_id')
                    ->on('sub.tenant_id', '=', 'a.tenant_id');
            })
            ->join('classes as c', function ($j) {
                $j->on('c.id', '=', 'e.class_id')
                    ->on('c.tenant_id', '=', 'a.tenant_id');
            })
            ->leftJoin('student_profiles as sp', function ($j) {
                $j->on('sp.user_id', '=', 'a.student_id')
                    ->on('sp.tenant_id', '=', 'a.tenant_id');
            })
            ->whereNotNull('e.answer_slip_released_at')
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
                'sp.first_name',
                'sp.last_name',
            ])
            ->first();

        if (! $attempt) return response()->json(['message' => 'Answer slip not available.'], 404);
        if ($attempt->status !== 'submitted') return response()->json(['message' => 'Answer slip not available.'], 422);

        $answers = TenantDB::table('exam_attempt_answers')
            ->where('attempt_id', $attemptId)
            ->orderBy('question_number')
            ->get();

        $studentName = trim(($attempt->last_name ?? '') . ' ' . ($attempt->first_name ?? ''));
        if (empty($studentName)) {
            $studentName = 'student-' . (string) $student->id;
        }

        $classSlug = Str::slug($attempt->class_name ?: 'class');
        $subjectSlug = Str::slug($attempt->subject_name ?: 'subject');
        $studentSlug = Str::slug($studentName);
        $filename = $studentSlug . '-' . $classSlug . '-' . $subjectSlug . '-exam-slip.pdf';

        $school = app('tenant.school');
        $schoolName = $school?->name;

        $pdf = Pdf::loadView('pdf.exam-answer-slip', [
            'attempt' => $attempt,
            'studentName' => $studentName,
            'answers' => $answers,
            'schoolName' => $schoolName,
        ]);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function resolveCode(Request $request)
    {
        $tenantId = TenantContext::id();
        $student = $request->user();
        $data = $request->validate([
            'code' => ['required', 'string', 'size:6'],
            'continue_key' => ['nullable', 'string', 'min:6', 'max:64'],
        ]);

        $exam = DB::table('exams as e')
            ->where('e.tenant_id', $tenantId)
            ->join('exam_question_submissions as s', function ($j) {
                $j->on('s.id', '=', 'e.submission_id')
                    ->on('s.tenant_id', '=', 'e.tenant_id');
            })
            ->join('classes as c', function ($j) {
                $j->on('c.id', '=', 'e.class_id')
                    ->on('c.tenant_id', '=', 'e.tenant_id');
            })
            ->join('subjects as sub', function ($j) {
                $j->on('sub.id', '=', 'e.subject_id')
                    ->on('sub.tenant_id', '=', 'e.tenant_id');
            })
            ->where('e.code', strtoupper($data['code']))
            ->select([
                'e.id',
                'e.code',
                'e.exam_type',
                'e.duration_minutes',
                'e.question_count',
                'e.status',
                'e.started_at',
                'e.ended_at',
                'e.class_id',
                'c.name as class_name',
                'e.subject_id',
                'sub.name as subject_name',
                'sub.code as subject_code',
                's.paper_pdf_path',
            ])
            ->first();

        if (! $exam) return response()->json(['message' => 'Invalid exam code.'], 404);
        if ($exam->status !== 'live') return response()->json(['message' => 'Exam is not live yet.'], 403);

        $studentClassId = (int) (TenantDB::table('student_profiles')->where('user_id', $student->id)->value('current_class_id') ?? 0);
        if (! $studentClassId || $studentClassId !== (int) $exam->class_id) {
            return response()->json(['message' => 'You are not eligible for this exam.'], 403);
        }

        $attempt = TenantDB::table('exam_attempts')->where('exam_id', $exam->id)->where('student_id', $student->id)->first();

        if (! $attempt) {
            // Create attempt and return continue key once (but don't show it to student - only store internally).
            $continueKey = strtoupper(Str::random(12));
            DB::table('exam_attempts')->insert([
                'tenant_id' => $tenantId,
                'exam_id' => $exam->id,
                'student_id' => $student->id,
                'continue_token_hash' => hash('sha256', $continueKey),
                'continue_key_plain' => $continueKey, // Store plaintext for admin monitoring (not for student)
                'status' => 'not_started',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'exam' => $this->examResponse($exam),
                'attempt' => [
                    'status' => 'not_started',
                    'continue_key' => $continueKey, // Returned but frontend won't show it to student
                ],
            ]);
        }

        // Existing attempt: require continue_key if in_progress or not_started, allow if submitted (view only).
        if ($attempt->status !== 'submitted') {
            if (empty($data['continue_key'])) {
                return response()->json([
                    'message' => 'Continue key is required to resume this exam. Contact your teacher/admin for your continue key.',
                    'requires_continue_key' => true,
                ], 422);
            }
            if (hash('sha256', $data['continue_key']) !== $attempt->continue_token_hash) {
                return response()->json(['message' => 'Invalid continue key.'], 403);
            }
            // Valid continue key - allow them to continue
        }

        return response()->json([
            'exam' => $this->examResponse($exam),
            'attempt' => [
                'status' => $attempt->status,
                'started_at' => $attempt->started_at,
                'submitted_at' => $attempt->submitted_at,
                'objective_score' => $attempt->objective_score,
                'total_score' => $attempt->total_score,
                'continue_key_verified' => ! empty($data['continue_key']),
            ],
        ]);
    }

    public function begin(Request $request, int $examId)
    {
        $tenantId = TenantContext::id();
        $student = $request->user();
        $data = $request->validate([
            'continue_key' => ['required', 'string', 'min:6', 'max:64'],
        ]);

        $exam = DB::table('exams as e')
            ->where('e.tenant_id', $tenantId)
            ->join('exam_question_submissions as s', function ($j) {
                $j->on('s.id', '=', 'e.submission_id')
                    ->on('s.tenant_id', '=', 'e.tenant_id');
            })
            ->where('e.id', $examId)
            ->select(['e.*', 's.paper_pdf_path'])
            ->first();
        if (! $exam) return response()->json(['message' => 'Exam not found.'], 404);
        if ($exam->status !== 'live') return response()->json(['message' => 'Exam is not live.'], 403);

        $studentClassId = (int) (TenantDB::table('student_profiles')->where('user_id', $student->id)->value('current_class_id') ?? 0);
        if (! $studentClassId || $studentClassId !== (int) $exam->class_id) {
            return response()->json(['message' => 'You are not eligible for this exam.'], 403);
        }

        $attempt = TenantDB::table('exam_attempts')->where('exam_id', $examId)->where('student_id', $student->id)->first();
        if (! $attempt) return response()->json(['message' => 'Attempt not found. Enter code again.'], 404);
        if (hash('sha256', $data['continue_key']) !== $attempt->continue_token_hash) {
            return response()->json(['message' => 'Invalid continue key.'], 403);
        }
        // Allow resume if submitted less than 2 minutes ago (likely auto-submit from page leave)
        // Otherwise, if fully submitted, don't allow resume
        if ($attempt->status === 'submitted') {
            $submittedAt = $attempt->submitted_at ? Carbon::parse($attempt->submitted_at) : null;
            $now = Carbon::now();
            if ($submittedAt && $submittedAt->diffInMinutes($now) < 2) {
                // Recently submitted (likely auto-submit) - allow resume
                DB::table('exam_attempts')->where('tenant_id', $tenantId)->where('id', $attempt->id)->update([
                    'status' => 'in_progress',
                    'submitted_at' => null,
                    'updated_at' => now(),
                ]);
                $attempt->status = 'in_progress';
                $attempt->submitted_at = null;
            } else {
                return response()->json(['message' => 'This exam has already been submitted and cannot be resumed.'], 409);
            }
        }

        if (! $attempt->started_at) {
            DB::table('exam_attempts')->where('tenant_id', $tenantId)->where('id', $attempt->id)->update([
                'status' => 'in_progress',
                'started_at' => now(),
                'last_seen_at' => now(),
                'updated_at' => now(),
            ]);
            $attempt->started_at = now();
            $attempt->status = 'in_progress';
        } else {
            DB::table('exam_attempts')->where('tenant_id', $tenantId)->where('id', $attempt->id)->update([
                'status' => 'in_progress',
                'last_seen_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $startedAt = $attempt->started_at instanceof Carbon
            ? $attempt->started_at
            : Carbon::parse((string) $attempt->started_at);
        $endsAt = (clone $startedAt)->addMinutes((int) $exam->duration_minutes);

        $questions = [];
        if ($exam->exam_type === 'objective') {
            $questions = TenantDB::table('exam_objective_questions')
                ->where('exam_id', $examId)
                ->orderBy('question_number')
                ->get()
                ->map(function ($q) {
                    $opts = [];
                    if (! empty($q->option_a)) $opts['A'] = trim((string) $q->option_a);
                    if (! empty($q->option_b)) $opts['B'] = trim((string) $q->option_b);
                    if (! empty($q->option_c)) $opts['C'] = trim((string) $q->option_c);
                    if (! empty($q->option_d)) $opts['D'] = trim((string) $q->option_d);
                    if (! empty($q->option_e)) $opts['E'] = trim((string) $q->option_e);
                    
                    return [
                        'number' => (int) $q->question_number,
                        'question' => trim((string) $q->question_text),
                        'options' => $opts,
                    ];
                })
                ->values()
                ->filter(fn ($q) => ! empty($q['question'])); // Only include questions with text
        }

        $existingAnswers = DB::table('exam_attempt_answers')
            ->where('tenant_id', $tenantId)
            ->where('attempt_id', $attempt->id)
            ->orderBy('question_number')
            ->get()
            ->map(fn ($a) => [
                'question_number' => $a->question_number,
                'objective_choice' => $a->objective_choice,
                'answer_text' => $a->answer_text,
            ]);

        // Build PDF URL using request's scheme and host
        $scheme = $request->getScheme();
        $host = $request->getHost();
        $port = $request->getPort();
        $baseUrl = $scheme . '://' . $host . ($port && !in_array($port, [80, 443]) ? ':' . $port : '');
        $pdfUrl = $baseUrl . "/api/tenant/student/exams/{$examId}/paper";

        return response()->json([
            'exam' => [
                'id' => $exam->id,
                'code' => $exam->code,
                'exam_type' => $exam->exam_type,
                'duration_minutes' => (int) $exam->duration_minutes,
                'question_count' => $exam->question_count ? (int) $exam->question_count : null,
                'paper_pdf_url' => $pdfUrl,
            ],
            'attempt' => [
                'id' => $attempt->id,
                'status' => $attempt->status,
                'started_at' => $attempt->started_at,
                'ends_at' => $endsAt->toISOString(),
            ],
            'questions' => $questions,
            'answers' => $existingAnswers,
        ]);
    }

    public function paper(Request $request, int $examId)
    {
        $tenantId = TenantContext::id();
        $student = $request->user();
        $exam = DB::table('exams as e')
            ->where('e.tenant_id', $tenantId)
            ->join('exam_question_submissions as s', function ($j) {
                $j->on('s.id', '=', 'e.submission_id')
                    ->on('s.tenant_id', '=', 'e.tenant_id');
            })
            ->where('e.id', $examId)
            ->select(['e.class_id', 'e.status', 's.paper_pdf_path'])
            ->first();

        if (! $exam) return response()->json(['message' => 'Exam not found.'], 404);
        if ($exam->status !== 'live') return response()->json(['message' => 'Exam is not live.'], 403);

        $studentClassId = (int) (TenantDB::table('student_profiles')->where('user_id', $student->id)->value('current_class_id') ?? 0);
        if (! $studentClassId || $studentClassId !== (int) $exam->class_id) {
            return response()->json(['message' => 'You are not eligible for this exam.'], 403);
        }

        $path = Storage::disk('public')->path($exam->paper_pdf_path);
        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="exam-paper.pdf"',
        ]);
    }

    public function heartbeat(Request $request, int $examId)
    {
        $tenantId = TenantContext::id();
        $student = $request->user();
        $data = $request->validate([
            'continue_key' => ['required', 'string', 'min:6', 'max:64'],
        ]);

        $attempt = TenantDB::table('exam_attempts')->where('exam_id', $examId)->where('student_id', $student->id)->first();
        if (! $attempt) return response()->json(['message' => 'Attempt not found.'], 404);
        if (hash('sha256', $data['continue_key']) !== $attempt->continue_token_hash) {
            return response()->json(['message' => 'Invalid continue key.'], 403);
        }
        if ($attempt->status === 'submitted') return response()->json(['message' => 'Already submitted.'], 409);

        DB::table('exam_attempts')->where('tenant_id', $tenantId)->where('id', $attempt->id)->update([
            'last_seen_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function saveAnswers(Request $request, int $examId)
    {
        $tenantId = TenantContext::id();
        $student = $request->user();
        $data = $request->validate([
            'continue_key' => ['required', 'string', 'min:6', 'max:64'],
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_number' => ['required', 'integer', 'min:1'],
            'answers.*.objective_choice' => ['nullable', 'string', 'max:2'],
            'answers.*.answer_text' => ['nullable', 'string'],
        ]);

        $exam = TenantDB::table('exams')->where('id', $examId)->first();
        if (! $exam) return response()->json(['message' => 'Exam not found.'], 404);

        $attempt = TenantDB::table('exam_attempts')->where('exam_id', $examId)->where('student_id', $student->id)->first();
        if (! $attempt) return response()->json(['message' => 'Attempt not found.'], 404);
        if (hash('sha256', $data['continue_key']) !== $attempt->continue_token_hash) {
            return response()->json(['message' => 'Invalid continue key.'], 403);
        }
        if ($attempt->status === 'submitted') return response()->json(['message' => 'Already submitted.'], 409);

        DB::transaction(function () use ($tenantId, $attempt, $data) {
            foreach ($data['answers'] as $a) {
                DB::table('exam_attempt_answers')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'attempt_id' => $attempt->id, 'question_number' => (int) $a['question_number']],
                    [
                        'tenant_id' => $tenantId,
                        'objective_choice' => isset($a['objective_choice']) ? strtoupper((string) $a['objective_choice']) : null,
                        'answer_text' => $a['answer_text'] ?? null,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            DB::table('exam_attempts')->where('tenant_id', $tenantId)->where('id', $attempt->id)->update([
                'last_seen_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Saved.']);
    }

    public function submit(Request $request, int $examId)
    {
        $tenantId = TenantContext::id();
        $student = $request->user();
        $data = $request->validate([
            'continue_key' => ['required', 'string', 'min:6', 'max:64'],
            'answers' => ['nullable', 'array'],
            'answers.*.question_number' => ['required_with:answers', 'integer', 'min:1'],
            'answers.*.objective_choice' => ['nullable', 'string', 'max:2'],
            'answers.*.answer_text' => ['nullable', 'string'],
        ]);

        $exam = TenantDB::table('exams')->where('id', $examId)->first();
        if (! $exam) return response()->json(['message' => 'Exam not found.'], 404);
        if ($exam->status !== 'live') return response()->json(['message' => 'Exam is not live.'], 403);

        $attempt = TenantDB::table('exam_attempts')->where('exam_id', $examId)->where('student_id', $student->id)->first();
        if (! $attempt) return response()->json(['message' => 'Attempt not found.'], 404);
        if (hash('sha256', $data['continue_key']) !== $attempt->continue_token_hash) {
            return response()->json(['message' => 'Invalid continue key.'], 403);
        }
        if ($attempt->status === 'submitted') return response()->json(['message' => 'Already submitted.'], 409);

        // Save provided answers (if any)
        if (! empty($data['answers'])) {
            foreach ($data['answers'] as $a) {
                DB::table('exam_attempt_answers')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'attempt_id' => $attempt->id, 'question_number' => (int) $a['question_number']],
                    [
                        'tenant_id' => $tenantId,
                        'objective_choice' => isset($a['objective_choice']) ? strtoupper((string) $a['objective_choice']) : null,
                        'answer_text' => $a['answer_text'] ?? null,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        $objectiveScore = null;
        if ($exam->exam_type === 'objective' && $exam->answer_key) {
            $key = is_string($exam->answer_key) ? json_decode($exam->answer_key, true) ?? [] : (array) $exam->answer_key;
            $answers = TenantDB::table('exam_attempt_answers')->where('attempt_id', $attempt->id)->get();
            $score = 0;
            $total = 0;
            foreach ($key as $qno => $item) {
                $total++;
                $given = $answers->firstWhere('question_number', (int) $qno);
                
                // Handle new format: { answer: 'A', mark: 5 } or old format: 'A'
                $correctAnswer = is_array($item) ? ($item['answer'] ?? null) : $item;
                $mark = is_array($item) && isset($item['mark']) ? (int) $item['mark'] : null;
                
                if ($given && $correctAnswer && strtoupper((string) $given->objective_choice) === strtoupper((string) $correctAnswer)) {
                    if ($mark !== null && $mark > 0) {
                        // Use per-question mark if available
                        $score += $mark;
                    } else {
                        // Fallback to marks_per_question if provided, otherwise default to 1
                        $mpq = max(1, (int) ($exam->marks_per_question ?? 1));
                        $score += $mpq;
                    }
                }
            }
            if ($total > 0) {
                // Cap at 100 to fit results total model
                $objectiveScore = min(100, $score);
            }
        }

        DB::table('exam_attempts')->where('tenant_id', $tenantId)->where('id', $attempt->id)->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'objective_score' => $objectiveScore,
            'total_score' => $objectiveScore, // For objective exams, keep total_score aligned for downstream logic
            'marked_at' => $objectiveScore !== null ? now() : null,
            'updated_at' => now(),
        ]);

        // If objective score is known, push into student_scores.exam
        if ($objectiveScore !== null) {
            $studentClassId = (int) (TenantDB::table('student_profiles')->where('user_id', $student->id)->value('current_class_id') ?? 0);
            $this->upsertStudentScoreExam($tenantId, $student->id, $exam->subject_id, $exam->academic_session_id, $exam->term_id, $objectiveScore, $studentClassId);
        }

        return response()->json([
            'message' => 'Submitted.',
            'objective_score' => $objectiveScore,
        ]);
    }

    private function examResponse($exam): array
    {
        return [
            'id' => $exam->id,
            'code' => $exam->code,
            'exam_type' => $exam->exam_type,
            'duration_minutes' => (int) $exam->duration_minutes,
            'question_count' => $exam->question_count ? (int) $exam->question_count : null,
            'status' => $exam->status,
            'class_id' => (int) $exam->class_id,
            'class_name' => $exam->class_name ?? null,
            'subject_id' => (int) $exam->subject_id,
            'subject_name' => $exam->subject_name ?? null,
            'subject_code' => $exam->subject_code ?? null,
            'paper_pdf_url' => url("/api/tenant/student/exams/{$exam->id}/paper"),
        ];
    }

    private function upsertStudentScoreExam(string $tenantId, int $studentId, int $subjectId, int $sessionId, int $termId, int $examScore, int $studentClassId): void
    {
        $existing = DB::table('student_scores')->where([
            'tenant_id' => $tenantId,
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
                'tenant_id' => $tenantId,
                'student_id' => $studentId,
                'subject_id' => $subjectId,
                'academic_session_id' => $sessionId,
                'term_id' => $termId,
            ],
            [
                'tenant_id' => $tenantId,
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


