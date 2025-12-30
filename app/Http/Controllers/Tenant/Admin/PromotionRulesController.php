<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PromotionRulesController extends Controller
{
    public function index()
    {
        $items = DB::table('promotion_rules')
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get()
            ->map(function ($r) {
                $criteria = $r->criteria;
                if (is_string($criteria)) $criteria = json_decode($criteria, true) ?? null;
                return [
                    'id' => $r->id,
                    'name' => $r->name,
                    'type' => $r->type,
                    'description' => $r->description,
                    'is_active' => (bool) $r->is_active,
                    'criteria' => $criteria,
                    'created_at' => $r->created_at,
                ];
            });

        return response()->json(['data' => $items]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:all_students_promote,minimum_grades_required,minimum_average_score,minimum_subjects_passed'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'criteria' => ['nullable', 'array'],
        ]);

        return DB::transaction(function () use ($request, $data) {
            if (! empty($data['is_active'])) {
                DB::table('promotion_rules')->update(['is_active' => false, 'updated_at' => now()]);
            }

            $id = DB::table('promotion_rules')->insertGetId([
                'name' => $data['name'],
                'type' => $data['type'],
                'description' => $data['description'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? false),
                'criteria' => isset($data['criteria']) ? json_encode($data['criteria']) : null,
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json(['data' => ['id' => $id]], 201);
        });
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:all_students_promote,minimum_grades_required,minimum_average_score,minimum_subjects_passed'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'criteria' => ['nullable', 'array'],
        ]);

        return DB::transaction(function () use ($id, $data) {
            $exists = DB::table('promotion_rules')->where('id', $id)->exists();
            if (! $exists) {
                return response()->json(['message' => 'Promotion rule not found.'], 404);
            }

            if (! empty($data['is_active'])) {
                DB::table('promotion_rules')->update(['is_active' => false, 'updated_at' => now()]);
            }

            DB::table('promotion_rules')->where('id', $id)->update([
                'name' => $data['name'],
                'type' => $data['type'],
                'description' => $data['description'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? false),
                'criteria' => isset($data['criteria']) ? json_encode($data['criteria']) : null,
                'updated_at' => now(),
            ]);

            return response()->json(['message' => 'Promotion rule updated.']);
        });
    }

    public function destroy(int $id)
    {
        $deleted = DB::table('promotion_rules')->where('id', $id)->delete();
        if (! $deleted) {
            return response()->json(['message' => 'Promotion rule not found.'], 404);
        }
        return response()->json(['message' => 'Promotion rule deleted.']);
    }

    public function run(Request $request)
    {
        $data = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'class_ids' => ['nullable', 'array', 'min:1'],
            'class_ids.*' => ['integer', 'exists:classes,id'],
            'promotion_rule_id' => ['nullable', 'integer', 'exists:promotion_rules,id'],
        ]);

        $currentSession = DB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? DB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        if (! $currentSession || ! $currentTerm) {
            return response()->json(['message' => 'Current academic session/term is not set.'], 400);
        }

        $termName = strtolower(trim((string) ($currentTerm->name ?? '')));
        if ($termName !== 'third term') {
            return response()->json(['message' => 'Promotion can only be run in Third Term.'], 422);
        }

        $classIds = [];
        if (!empty($data['class_ids']) && is_array($data['class_ids'])) {
            $classIds = array_values(array_unique(array_map('intval', $data['class_ids'])));
        } elseif (!empty($data['class_id'])) {
            $classIds = [(int) $data['class_id']];
        }
        if (empty($classIds)) {
            return response()->json(['message' => 'Please select at least one class.'], 422);
        }

        $rule = null;
        if (! empty($data['promotion_rule_id'])) {
            $rule = DB::table('promotion_rules')->where('id', (int) $data['promotion_rule_id'])->first();
        } else {
            $rule = DB::table('promotion_rules')->where('is_active', true)->orderByDesc('id')->first();
        }

        if (! $rule) {
            return response()->json(['message' => 'No promotion rule selected (and no active rule found).'], 422);
        }

        // Run multiple classes in one click (processed top-down to avoid double-promotion).
        $classIdRows = DB::table('classes')->whereIn('id', $classIds)->get(['id', 'name']);
        $order = $classIdRows
            ->map(function ($c) {
                $p = $this->parseClassSeries((string) $c->name);
                $seriesRank = $p ? $this->seriesRank((string) $p['series']) : -1;
                $num = $p ? (int) $p['num'] : -1;
                return [
                    'id' => (int) $c->id,
                    'name' => (string) $c->name,
                    'series_rank' => $seriesRank,
                    'num' => $num,
                ];
            })
            ->sortByDesc('id')
            ->sortByDesc('num')
            ->sortByDesc('series_rank')
            ->values();

        $results = [];
        foreach ($order as $row) {
            $results[] = $this->runPromotionForClass(
                (int) $row['id'],
                (string) $row['name'],
                $rule,
                $currentSession,
                $currentTerm
            );
        }

        // If single-class request, keep the old response shape for compatibility.
        if (count($results) === 1) {
            $r = $results[0];
            if ($r['ok']) {
                return response()->json([
                    'message' => $r['message'],
                    'summary' => $r['summary'],
                ]);
            }
            return response()->json([
                'message' => $r['message'],
                'missing' => $r['missing'] ?? null,
            ], (int) ($r['status_code'] ?? 422));
        }

        return response()->json([
            'message' => 'Promotion run completed.',
            'results' => $results,
        ]);

        $criteria = $rule->criteria;
        if (is_string($criteria)) $criteria = json_decode($criteria, true) ?? [];
        if (! is_array($criteria)) $criteria = [];

        $passMark = (int) ($criteria['pass_mark'] ?? 40);
        $minAverage = (float) ($criteria['min_average'] ?? 50);
        $minSubjectsPassed = (int) ($criteria['min_subjects_passed'] ?? (int) ceil($subjects->count() * 0.5));
        $minGrade = strtoupper((string) ($criteria['min_grade'] ?? 'E'));

        // (the actual work is moved into runPromotionForClass())
    }

    private function meetsPromotionRule(string $type, $scores, array $opts): bool
    {
        if ($type === 'all_students_promote') {
            return true;
        }

        if ($type === 'minimum_average_score') {
            // Prefer session final average (T1+T2+T3)/3 if provided; fallback to current-term average.
            $final = $opts['session_final_average'] ?? null;
            if ($final !== null) {
                return (float) $final >= (float) $opts['min_average'];
            }

            $totalScore = $scores->sum('total');
            $avg = $scores->count() > 0 ? ($totalScore / $scores->count()) : 0;
            return $avg >= (float) $opts['min_average'];
        }

        if ($type === 'minimum_subjects_passed') {
            $passedCount = $scores->filter(function ($x) use ($opts) {
                return $x->total !== null && (int) $x->total >= (int) $opts['pass_mark'];
            })->count();
            return $passedCount >= (int) $opts['min_subjects_passed'];
        }

        if ($type === 'minimum_grades_required') {
            $minGrade = strtoupper((string) $opts['min_grade']);
            $minRank = $this->gradeRank($minGrade);
            foreach ($scores as $sc) {
                $classId = (int) ($opts['class_id'] ?? 0);
                $totalInt = is_numeric($sc->total) ? (int) round((float) $sc->total) : null;
                $dynamic = ($classId && $totalInt !== null)
                    ? GradingConfigsController::gradeForClassTotal($classId, $totalInt)
                    : null;
                $g = strtoupper((string) ($dynamic ?: ($sc->grade ?? 'F')));
                if ($this->gradeRank($g) < $minRank) return false;
            }
            return true;
        }

        return false;
    }

    private function gradeRank(string $grade): int
    {
        // A highest.
        return match (strtoupper($grade)) {
            'A' => 6,
            'B' => 5,
            'C' => 4,
            'D' => 3,
            'E' => 2,
            default => 1, // F or unknown
        };
    }

    private function teacherMapForClassSubjects(int $classId): array
    {
        // Teachers eligible for (class_id, subject_id) are those assigned to that class AND assigned to that subject.
        $classTable = Schema::hasTable('teacher_class') ? 'teacher_class' : (Schema::hasTable('teacher_classes') ? 'teacher_classes' : null);
        $subjectTable = Schema::hasTable('teacher_subject') ? 'teacher_subject' : (Schema::hasTable('teacher_subjects') ? 'teacher_subjects' : null);
        if (! $classTable || ! $subjectTable) return [];

        $rows = DB::table($classTable . ' as tc')
            ->join($subjectTable . ' as ts', 'ts.teacher_id', '=', 'tc.teacher_id')
            ->join('users as u', 'u.id', '=', 'tc.teacher_id')
            ->where('tc.class_id', $classId)
            ->where('u.role', 'teacher')
            ->select([
                'ts.subject_id',
                'u.id as teacher_id',
                'u.name as teacher_name',
                'u.username as teacher_username',
            ])
            ->orderBy('u.name')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[$r->subject_id] ??= [];
            $map[$r->subject_id][] = [
                'teacher_id' => $r->teacher_id,
                'name' => $r->teacher_name,
                'username' => $r->teacher_username,
            ];
        }
        return $map;
    }

    private function seriesRank(string $series): int
    {
        return match (strtolower($series)) {
            'creche' => 1,
            'nursery' => 2,
            'kg' => 3,
            'primary' => 4,
            'basic' => 4,
            'grade' => 4,
            'jss' => 5,
            'sss' => 6,
            default => 0,
        };
    }

    private function runPromotionForClass(int $classId, string $className, $rule, $currentSession, $currentTerm): array
    {
        $alreadyRun = DB::table('student_promotions')
            ->where('academic_session_id', $currentSession->id)
            ->where('term_id', $currentTerm->id)
            ->where('from_class_id', $classId)
            ->exists();
        if ($alreadyRun) {
            return [
                'ok' => false,
                'status_code' => 409,
                'class_id' => $classId,
                'class_name' => $className,
                'message' => 'Promotion has already been run for this class in the current term.',
            ];
        }

        // Exclude students already processed by promotion in this session/term (prevents double-promotion).
        $students = DB::table('users')
            ->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->leftJoin('student_promotions as sp', function ($join) use ($currentSession, $currentTerm) {
                $join->on('sp.student_id', '=', 'users.id')
                    ->where('sp.academic_session_id', '=', $currentSession->id)
                    ->where('sp.term_id', '=', $currentTerm->id);
            })
            ->where('users.role', 'student')
            ->where('student_profiles.current_class_id', $classId)
            ->whereNull('sp.id')
            ->select([
                'users.id as student_id',
                'users.admission_number',
                'student_profiles.first_name',
                'student_profiles.last_name',
            ])
            ->orderBy('student_profiles.last_name')
            ->get();

        if ($students->isEmpty()) {
            return [
                'ok' => false,
                'status_code' => 422,
                'class_id' => $classId,
                'class_name' => $className,
                'message' => 'No eligible students found in this class (they may have already been promoted this term).',
            ];
        }

        $studentIds = $students->pluck('student_id')->map(fn ($x) => (int) $x)->values()->all();

        // Determine subjects (same logic as before).
        $subjectIds = [];
        if (Schema::hasTable('class_subject')) {
            $subjectIds = DB::table('class_subject')
                ->where('class_id', $classId)
                ->pluck('subject_id')
                ->map(fn ($x) => (int) $x)
                ->unique()
                ->values()
                ->all();
        }
        if (empty($subjectIds) && Schema::hasTable('student_subject')) {
            $subjectIds = DB::table('student_subject')
                ->whereIn('student_id', $studentIds)
                ->pluck('subject_id')
                ->map(fn ($x) => (int) $x)
                ->unique()
                ->values()
                ->all();
        }
        if (empty($subjectIds)) {
            $subjectIds = DB::table('student_scores')
                ->whereIn('student_id', $studentIds)
                ->where('academic_session_id', $currentSession->id)
                ->where('term_id', $currentTerm->id)
                ->whereNotNull('total')
                ->pluck('subject_id')
                ->map(fn ($x) => (int) $x)
                ->unique()
                ->values()
                ->all();
        }
        if (empty($subjectIds)) {
            return [
                'ok' => false,
                'status_code' => 422,
                'class_id' => $classId,
                'class_name' => $className,
                'message' =>
                    'Promotion cannot run because the system cannot determine the subjects for this class. ' .
                    'Please configure subjects for the class (or assign subjects to students) and ensure results are recorded.',
            ];
        }

        $subjects = DB::table('subjects')
            ->whereIn('id', $subjectIds)
            ->select(['id', 'name', 'code'])
            ->orderBy('name')
            ->get();
        if ($subjects->isEmpty()) {
            return [
                'ok' => false,
                'status_code' => 422,
                'class_id' => $classId,
                'class_name' => $className,
                'message' => 'Promotion cannot run because the determined subjects are missing from the subjects table.',
            ];
        }

        // Completeness check.
        $scoresMap = DB::table('student_scores')
            ->whereIn('student_id', $studentIds)
            ->whereIn('subject_id', $subjectIds)
            ->where('academic_session_id', $currentSession->id)
            ->where('term_id', $currentTerm->id)
            ->get(['student_id', 'subject_id', 'total'])
            ->mapWithKeys(fn ($r) => [((int) $r->student_id) . '-' . ((int) $r->subject_id) => $r]);

        $missingRows = [];
        foreach ($students as $st) {
            $sid = (int) $st->student_id;
            foreach ($subjects as $sub) {
                $subId = (int) $sub->id;
                $key = $sid . '-' . $subId;
                $sc = $scoresMap->get($key);
                if (! $sc || $sc->total === null) {
                    $missingRows[] = [
                        'class_id' => $classId,
                        'class_name' => $className,
                        'student_id' => $sid,
                        'admission_number' => $st->admission_number,
                        'student_name' => trim($st->last_name . ' ' . $st->first_name),
                        'subject_id' => $subId,
                        'subject_name' => $sub->name,
                        'subject_code' => $sub->code,
                    ];
                }
            }
        }
        if (!empty($missingRows)) {
            $teacherMap = $this->teacherMapForClassSubjects($classId);
            $rows = collect($missingRows)->map(function ($m) use ($teacherMap) {
                $key = (int) $m['subject_id'];
                return [
                    ...$m,
                    'teachers' => $teacherMap[$key] ?? [],
                ];
            })->values();

            return [
                'ok' => false,
                'status_code' => 422,
                'class_id' => $classId,
                'class_name' => $className,
                'message' => 'Promotion cannot be run because some results are not fully recorded for this class.',
                'missing' => $rows,
            ];
        }

        $criteria = $rule->criteria;
        if (is_string($criteria)) $criteria = json_decode($criteria, true) ?? [];
        if (! is_array($criteria)) $criteria = [];

        $passMark = (int) ($criteria['pass_mark'] ?? 40);
        $minAverage = (float) ($criteria['min_average'] ?? 50);
        $minSubjectsPassed = (int) ($criteria['min_subjects_passed'] ?? (int) ceil($subjects->count() * 0.5));
        $minGrade = strtoupper((string) ($criteria['min_grade'] ?? 'E'));

        $nextClassId = $this->nextClassIdForPromotion($classId);

        $promoted = 0;
        $repeated = 0;
        $graduated = 0;

        DB::transaction(function () use (
            $students,
            $subjects,
            $currentSession,
            $currentTerm,
            $classId,
            $nextClassId,
            $rule,
            $criteria,
            $passMark,
            $minAverage,
            $minSubjectsPassed,
            $minGrade,
            &$promoted,
            &$repeated,
            &$graduated
        ) {
            $firstTermId = DB::table('terms')
                ->where('academic_session_id', $currentSession->id)
                ->whereRaw('LOWER(TRIM(name)) = ?', ['first term'])
                ->value('id');
            $secondTermId = DB::table('terms')
                ->where('academic_session_id', $currentSession->id)
                ->whereRaw('LOWER(TRIM(name)) = ?', ['second term'])
                ->value('id');
            $thirdTermId = $currentTerm->id;

            foreach ($students as $s) {
                $scores = DB::table('student_scores')
                    ->where('student_id', $s->student_id)
                    ->where('academic_session_id', $currentSession->id)
                    ->where('term_id', $currentTerm->id)
                    ->whereIn('subject_id', $subjects->pluck('id')->all())
                    ->get();

                $totalScore = $scores->sum('total');
                $avg = $scores->count() > 0 ? ($totalScore / $scores->count()) : 0;
                $passedCount = $scores->filter(function ($x) use ($passMark) {
                    return $x->total !== null && (int) $x->total >= $passMark;
                })->count();

                $opts = [
                    'class_id' => $classId,
                    'pass_mark' => $passMark,
                    'min_average' => $minAverage,
                    'min_subjects_passed' => $minSubjectsPassed,
                    'min_grade' => $minGrade,
                ];

                if ($rule->type === 'minimum_average_score') {
                    $termAvg = function (?int $termId) use ($s, $subjects, $currentSession): ?float {
                        if (! $termId) return null;
                        $rows = DB::table('student_scores')
                            ->where('student_id', $s->student_id)
                            ->where('academic_session_id', $currentSession->id)
                            ->where('term_id', $termId)
                            ->whereIn('subject_id', $subjects->pluck('id')->all())
                            ->whereNotNull('total')
                            ->get(['total']);
                        if ($rows->count() === 0) return null;
                        return $rows->sum('total') / $rows->count();
                    };

                    $a1 = $termAvg($firstTermId);
                    $a2 = $termAvg($secondTermId);
                    $a3 = $termAvg($thirdTermId);
                    $final = ($a1 !== null && $a2 !== null && $a3 !== null) ? (($a1 + $a2 + $a3) / 3.0) : null;
                    $opts['session_final_average'] = $final;
                }

                $meets = $this->meetsPromotionRule($rule->type, $scores, $opts);

                $status = $meets ? 'promoted' : 'repeated';
                $toClassId = $meets ? ($nextClassId ? (int) $nextClassId : null) : null;
                if ($meets && ! $nextClassId) {
                    $status = 'graduated';
                }

                DB::table('student_promotions')->insert([
                    'promotion_rule_id' => $rule->id,
                    'student_id' => $s->student_id,
                    'academic_session_id' => $currentSession->id,
                    'term_id' => $currentTerm->id,
                    'from_class_id' => $classId,
                    'to_class_id' => $toClassId,
                    'status' => $status,
                    'summary' => json_encode([
                        'average' => round($avg, 2),
                        'session_final_average' => isset($opts['session_final_average']) && $opts['session_final_average'] !== null
                            ? round((float) $opts['session_final_average'], 2)
                            : null,
                        'passed_count' => $passedCount,
                        'total_subjects' => $scores->count(),
                        'criteria' => $criteria,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($status === 'promoted' && $toClassId) {
                    DB::table('student_profiles')
                        ->where('user_id', $s->student_id)
                        ->update(['current_class_id' => $toClassId, 'updated_at' => now()]);
                    $promoted++;
                } elseif ($status === 'graduated') {
                    $graduated++;
                } else {
                    $repeated++;
                }
            }
        });

        return [
            'ok' => true,
            'class_id' => $classId,
            'class_name' => $className,
            'message' => 'Promotion completed.',
            'summary' => [
                'class_id' => $classId,
                'class_name' => $className,
                'academic_session_id' => $currentSession->id,
                'term_id' => $currentTerm->id,
                'promotion_rule_id' => $rule->id,
                'promoted' => $promoted,
                'repeated' => $repeated,
                'graduated' => $graduated,
            ],
        ];
    }

    private function nextClassIdForPromotion(int $classId): ?int
    {
        $from = DB::table('classes')->where('id', $classId)->first(['id', 'name']);
        if (! $from) return null;

        $parsed = $this->parseClassSeries((string) $from->name);
        $all = DB::table('classes')->select(['id', 'name'])->get();

        // Fallback: next higher ID (legacy behavior) when name cannot be parsed.
        if (! $parsed) {
            $next = $all->filter(fn ($c) => (int) $c->id > $classId)->sortBy('id')->first();
            return $next ? (int) $next->id : null;
        }

        $series = $parsed['series'];
        $num = (int) $parsed['num'];
        $section = $parsed['section']; // may be null

        $targetNum = $num + 1;

        $candidates = $all->map(function ($c) {
            $p = $this->parseClassSeries((string) $c->name);
            return $p ? ['id' => (int) $c->id, 'name' => (string) $c->name, ...$p] : null;
        })->filter()->values();

        $matches = $candidates
            ->filter(fn ($c) => $c['series'] === $series && (int) $c['num'] === $targetNum)
            ->values();

        if ($matches->isEmpty()) {
            // No next level found in this series => graduate.
            return null;
        }

        if ($section) {
            $sameSection = $matches->first(fn ($c) => strtoupper((string) ($c['section'] ?? '')) === strtoupper($section));
            if ($sameSection) return (int) $sameSection['id'];
        }

        // Choose the first matching class (stable by name then id).
        $chosen = $matches->sortBy(fn ($c) => strtolower($c['name']))->sortBy('id')->first();
        return $chosen ? (int) $chosen['id'] : null;
    }

    private function parseClassSeries(string $name): ?array
    {
        $n = strtolower(trim($name));
        $n = preg_replace('/[\\._\\-]+/', ' ', $n);
        $n = preg_replace('/\\s+/', ' ', $n);

        $patterns = [
            // series => regex (captures: num token, optional section letter)
            ['series' => 'nursery', 're' => '/\\b(nursery|nurs|nsy)\\s*([0-9]+|zero|one|two|three|four|five|six)\\s*([a-z])?\\b/i'],
            ['series' => 'creche', 're' => '/\\b(creche|cr[eè]che|crèche)\\s*([0-9]+|zero|one|two|three)?\\s*([a-z])?\\b/i'],
            ['series' => 'kg', 're' => '/\\b(kg|kindergarten)\\s*([0-9]+|zero|one|two|three)?\\s*([a-z])?\\b/i'],
            ['series' => 'primary', 're' => '/\\b(primary|pry)\\s*([0-9]+|one|two|three|four|five|six|seven|eight|nine|ten)\\s*([a-z])?\\b/i'],
            ['series' => 'basic', 're' => '/\\b(basic)\\s*([0-9]+|one|two|three|four|five|six|seven|eight|nine|ten)\\s*([a-z])?\\b/i'],
            ['series' => 'grade', 're' => '/\\b(grade|gs)\\s*([0-9]+|one|two|three|four|five|six|seven|eight|nine|ten)\\s*([a-z])?\\b/i'],
            ['series' => 'jss', 're' => '/\\b(jss|js)\\s*([0-9]+|one|two|three)\\s*([a-z])?\\b/i'],
            ['series' => 'sss', 're' => '/\\b(sss|ss)\\s*([0-9]+|one|two|three)\\s*([a-z])?\\b/i'],
        ];

        foreach ($patterns as $p) {
            if (preg_match($p['re'], $n, $m)) {
                $numToken = $m[2] ?? null;
                $num = $this->parseNumberToken($numToken);
                if ($num === null) return null;
                $section = isset($m[3]) && $m[3] !== '' ? strtoupper($m[3]) : null;

                // Special-case: SS3 (or SSS3) ends secondary school; always graduate if no next class exists.
                return [
                    'series' => $p['series'],
                    'num' => $num,
                    'section' => $section,
                ];
            }
        }

        return null;
    }

    private function parseNumberToken($token): ?int
    {
        if ($token === null) return null;
        $t = strtolower(trim((string) $token));
        if ($t === '') return null;

        if (ctype_digit($t)) return (int) $t;

        $map = [
            'zero' => 0,
            'one' => 1,
            'two' => 2,
            'three' => 3,
            'four' => 4,
            'five' => 5,
            'six' => 6,
            'seven' => 7,
            'eight' => 8,
            'nine' => 9,
            'ten' => 10,
            'i' => 1,
            'ii' => 2,
            'iii' => 3,
            'iv' => 4,
            'v' => 5,
            'vi' => 6,
            'vii' => 7,
            'viii' => 8,
            'ix' => 9,
            'x' => 10,
        ];

        return $map[$t] ?? null;
    }
}


