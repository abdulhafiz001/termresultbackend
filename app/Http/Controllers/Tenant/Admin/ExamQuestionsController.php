<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExamQuestionsController extends Controller
{
    public function index(Request $request)
    {
        $currentSession = DB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? DB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        if (! $currentSession || ! $currentTerm) {
            return response()->json(['message' => 'Current academic session/term is not set.'], 400);
        }

        $data = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'status' => ['nullable', 'in:pending,approved,rejected'],
        ]);

        $q = DB::table('exam_question_submissions as s')
            ->join('classes as c', 'c.id', '=', 's.class_id')
            ->join('subjects as sub', 'sub.id', '=', 's.subject_id')
            ->join('users as t', 't.id', '=', 's.teacher_id')
            ->where('s.academic_session_id', $currentSession->id)
            ->where('s.term_id', $currentTerm->id);

        if (! empty($data['class_id'])) $q->where('s.class_id', (int) $data['class_id']);
        if (! empty($data['subject_id'])) $q->where('s.subject_id', (int) $data['subject_id']);
        if (! empty($data['status'])) $q->where('s.status', $data['status']);

        $items = $q->orderByDesc('s.id')->limit(500)->get([
            's.id',
            's.exam_type',
            's.duration_minutes',
            's.question_count',
            's.marks_per_question',
            's.status',
            's.rejection_reason',
            's.created_at',
            'c.name as class_name',
            'sub.name as subject_name',
            'sub.code as subject_code',
            't.name as teacher_name',
            't.username as teacher_username',
        ]);

        return response()->json([
            'meta' => [
                'academic_session_id' => $currentSession->id,
                'term_id' => $currentTerm->id,
            ],
            'data' => $items,
        ]);
    }

    public function downloadPaper(int $id)
    {
        $row = DB::table('exam_question_submissions')->where('id', $id)->first();
        if (! $row) return response()->json(['message' => 'Submission not found.'], 404);
        return Storage::disk('public')->download($row->paper_pdf_path, "exam-paper-{$id}.pdf");
    }

    public function downloadSource(int $id)
    {
        $row = DB::table('exam_question_submissions')->where('id', $id)->first();
        if (! $row) return response()->json(['message' => 'Submission not found.'], 404);
        if (! $row->source_file_path) return response()->json(['message' => 'No source file for this submission.'], 404);
        $name = $row->source_file_original_name ?: "exam-source-{$id}";
        return Storage::disk('public')->download($row->source_file_path, $name);
    }

    public function approve(Request $request, int $id)
    {
        $adminId = $request->user()->id;
        $row = DB::table('exam_question_submissions')->where('id', $id)->first();
        if (! $row) return response()->json(['message' => 'Submission not found.'], 404);
        if ($row->status === 'approved') {
            return response()->json(['message' => 'Submission already approved.'], 409);
        }
        if ($row->exam_type === 'objective') {
            if (! $row->source_file_path || ! Str::endsWith(strtolower((string) $row->source_file_path), '.txt')) {
                return response()->json(['message' => 'Objective exams require a TXT source file so the system can parse questions/options for students.'], 422);
            }
            if (empty($row->marks_per_question) || (int) $row->marks_per_question <= 0) {
                return response()->json(['message' => 'Objective exams require marks per question. Please ask the teacher to resubmit with marks per question.'], 422);
            }
        }

        return DB::transaction(function () use ($adminId, $row) {
            DB::table('exam_question_submissions')->where('id', $row->id)->update([
                'status' => 'approved',
                'rejection_reason' => null,
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

            $code = $this->generateCode();

            $questions = [];
            if ($row->exam_type === 'objective' && $row->source_file_path && Str::endsWith(strtolower($row->source_file_path), '.txt')) {
                $txt = Storage::disk('public')->get($row->source_file_path);
                $questions = $this->parseObjectiveTxt($txt);
                $count = count($questions);
                $mpq = (int) ($row->marks_per_question ?? 0);
                if ($count > 0 && $mpq > 0) {
                    $max = $count * $mpq;
                    if ($max > 100) {
                        return response()->json([
                            'message' => "Marks per question is too high. {$count} questions × {$mpq} marks = {$max} (must be ≤ 100). Ask the teacher to resubmit with a lower value.",
                        ], 422);
                    }
                }
            }

            $examId = DB::table('exams')->insertGetId([
                'submission_id' => $row->id,
                'code' => $code,
                'class_id' => $row->class_id,
                'subject_id' => $row->subject_id,
                'academic_session_id' => $row->academic_session_id,
                'term_id' => $row->term_id,
                'exam_type' => $row->exam_type,
                'duration_minutes' => $row->duration_minutes,
                'question_count' => $row->exam_type === 'objective' ? (count($questions) ?: null) : $row->question_count,
                'marks_per_question' => $row->marks_per_question,
                'status' => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Parse objective questions from TXT source file if available.
            if ($row->exam_type === 'objective' && !empty($questions)) {
                foreach ($questions as $q) {
                    DB::table('exam_objective_questions')->insert([
                        'exam_id' => $examId,
                        'question_number' => $q['number'],
                        'question_text' => $q['question'],
                        'option_a' => $q['options']['A'] ?? null,
                        'option_b' => $q['options']['B'] ?? null,
                        'option_c' => $q['options']['C'] ?? null,
                        'option_d' => $q['options']['D'] ?? null,
                        'option_e' => $q['options']['E'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            return response()->json([
                'message' => 'Exam question approved.',
                'data' => [
                    'exam_id' => $examId,
                    'code' => $code,
                ],
            ]);
        });
    }

    public function reject(Request $request, int $id)
    {
        $adminId = $request->user()->id;
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $row = DB::table('exam_question_submissions')->where('id', $id)->first();
        if (! $row) return response()->json(['message' => 'Submission not found.'], 404);

        DB::table('exam_question_submissions')->where('id', $id)->update([
            'status' => 'rejected',
            'rejection_reason' => $data['reason'],
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Submission rejected.']);
    }

    private function generateCode(): string
    {
        // 6-char code, retry if collision.
        for ($i = 0; $i < 20; $i++) {
            $code = strtoupper(Str::random(6));
            $exists = DB::table('exams')->where('code', $code)->exists();
            if (! $exists) return $code;
        }
        return strtoupper(Str::random(6));
    }

    private function parseObjectiveTxt(string $txt): array
    {
        $lines = preg_split("/\\r\\n|\\r|\\n/", $txt);
        $questions = [];
        $current = null;

        $flush = function () use (&$questions, &$current) {
            if (! $current) return;
            if (! isset($current['options'])) $current['options'] = [];
            $questions[] = $current;
            $current = null;
        };

        foreach ($lines as $raw) {
            $line = trim((string) $raw);
            if ($line === '') continue;

            if (preg_match('/^(\\d+)\\s*[\\.)]\\s*(.+)$/', $line, $m)) {
                $flush();
                $current = [
                    'number' => (int) $m[1],
                    'question' => trim($m[2]),
                    'options' => [],
                ];
                continue;
            }

            if ($current && preg_match('/^([A-Ea-e])\\s*[\\.)]\\s*(.+)$/', $line, $m)) {
                $opt = strtoupper($m[1]);
                $current['options'][$opt] = trim($m[2]);
                continue;
            }

            // Continuation line: append to last option if exists, else append to question.
            if ($current) {
                $lastOpt = null;
                if (! empty($current['options'])) {
                    $keys = array_keys($current['options']);
                    $lastOpt = end($keys);
                }
                if ($lastOpt) {
                    $current['options'][$lastOpt] = trim($current['options'][$lastOpt] . ' ' . $line);
                } else {
                    $current['question'] = trim($current['question'] . ' ' . $line);
                }
            }
        }

        $flush();
        return $questions;
    }

    public function delete(Request $request, int $id)
    {
        $row = DB::table('exam_question_submissions')->where('id', $id)->first();
        if (! $row) return response()->json(['message' => 'Submission not found.'], 404);
        if ($row->status !== 'rejected') {
            return response()->json(['message' => 'Only rejected submissions can be deleted.'], 422);
        }

        // Delete files from storage
        if ($row->paper_pdf_path) {
            Storage::disk('public')->delete($row->paper_pdf_path);
        }
        if ($row->source_file_path) {
            Storage::disk('public')->delete($row->source_file_path);
        }

        // Delete the record
        DB::table('exam_question_submissions')->where('id', $id)->delete();

        return response()->json(['message' => 'Submission deleted successfully.']);
    }
}


