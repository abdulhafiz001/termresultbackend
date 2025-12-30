<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Exports\StudentsExport;
use App\Imports\StudentsImport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;

class StudentsController extends Controller
{
    public function show(int $id)
    {
        $user = User::query()->where('role', 'student')->findOrFail($id);
        $profile = DB::table('student_profiles')->where('user_id', $user->id)->first();
        $class = $profile?->current_class_id
            ? DB::table('classes')->where('id', (int) $profile->current_class_id)->first()
            : null;

        $subjectRows = DB::table('student_subject')
            ->join('subjects', 'subjects.id', '=', 'student_subject.subject_id')
            ->where('student_subject.student_id', $user->id)
            ->select(['subjects.id', 'subjects.name', 'subjects.code'])
            ->orderBy('subjects.name')
            ->get();

        $subjectIds = $subjectRows->pluck('id')->map(fn ($v) => (int) $v)->values()->all();

        $teachersForClass = [];
        if ($class) {
            $teachersForClass = DB::table('teacher_class')
                ->join('users', 'users.id', '=', 'teacher_class.teacher_id')
                ->where('teacher_class.class_id', (int) $class->id)
                ->where('users.role', 'teacher')
                ->where('users.status', 'active')
                ->select(['users.id', 'users.name'])
                ->orderBy('users.name')
                ->get();
        }

        return response()->json([
            'data' => [
                'id' => $user->id,
                'admission_number' => $user->admission_number,
                'status' => $user->status,
                'restrictions' => $user->restrictions ?? [],
                'restriction_reason' => $user->restriction_reason,
                'first_name' => $profile->first_name ?? null,
                'last_name' => $profile->last_name ?? null,
                'middle_name' => $profile->middle_name ?? null,
                'gender' => $profile->gender ?? null,
                'date_of_birth' => $profile->date_of_birth ?? null,
                'email' => $profile->email ?? null,
                'phone' => $profile->phone ?? null,
                'address' => $profile->address ?? null,
                'current_class_id' => $profile->current_class_id ?? null,
                'class_name' => $class?->name,
                'subjects' => $subjectRows,
                'subject_ids' => $subjectIds,
                'teachers_for_class' => $teachersForClass,
            ],
        ]);
    }

    public function import(Request $request)
    {
        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'file' => ['required', 'file', 'max:5120'], // 5MB
            'subject_ids' => ['nullable', 'string'], // JSON string array (apply to all imported students)
        ]);

        $classId = (int) $data['class_id'];
        $file = $request->file('file');

        $subjectIds = [];
        if (!empty($data['subject_ids'])) {
            $decoded = json_decode($data['subject_ids'], true);
            if (is_array($decoded)) {
                $subjectIds = array_values(array_filter(array_map('intval', $decoded), fn ($v) => $v > 0));
            }
        }
        if (!empty($subjectIds)) {
            $existsCount = DB::table('subjects')->whereIn('id', $subjectIds)->count();
            if ($existsCount !== count($subjectIds)) {
                return response()->json(['message' => 'One or more selected subjects are invalid.'], 422);
            }
        }

        $sheets = Excel::toArray(new StudentsImport(), $file);
        $rows = $sheets[0] ?? [];

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            // WithHeadingRow gives associative arrays. Be defensive.
            $first = trim((string) ($row['first_name'] ?? ''));
            $last = trim((string) ($row['last_name'] ?? ''));
            $middle = trim((string) ($row['middle_name'] ?? '')) ?: null;
            $admission = trim((string) ($row['admission_number'] ?? ''));
            $email = trim((string) ($row['email'] ?? '')) ?: null;
            $phone = trim((string) ($row['phone'] ?? '')) ?: null;
            $dob = trim((string) ($row['date_of_birth'] ?? '')) ?: null;
            $gender = strtolower(trim((string) ($row['gender'] ?? ''))) ?: null;
            $address = trim((string) ($row['address'] ?? '')) ?: null;

            $rowNum = $i + 2; // +1 for 0-index, +1 for heading row

            if ($first === '' || $last === '' || $admission === '') {
                $skipped++;
                $errors[] = "Row {$rowNum}: first_name, last_name and admission_number are required.";
                continue;
            }

            if ($gender !== null && ! in_array($gender, ['male', 'female'], true)) {
                $skipped++;
                $errors[] = "Row {$rowNum}: gender must be 'male' or 'female'.";
                continue;
            }

            // Enforce unique admission number.
            if (User::query()->where('admission_number', $admission)->exists()) {
                $skipped++;
                $errors[] = "Row {$rowNum}: admission_number '{$admission}' already exists.";
                continue;
            }

            DB::transaction(function () use (
                $first, $last, $middle, $admission, $email, $phone, $dob, $gender, $address, $classId, $subjectIds, &$created
            ) {
                $user = User::create([
                    'name' => trim($first.' '.$last),
                    'admission_number' => $admission,
                    'role' => 'student',
                    'status' => 'active',
                    'password' => Hash::make('password'),
                ]);

                DB::table('student_profiles')->insert([
                    'user_id' => $user->id,
                    'first_name' => $first,
                    'last_name' => $last,
                    'middle_name' => $middle,
                    'date_of_birth' => $dob ?: null,
                    'gender' => $gender ?: null,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address,
                    'current_class_id' => $classId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if (!empty($subjectIds)) {
                    $rows = array_map(fn ($sid) => [
                        'student_id' => $user->id,
                        'subject_id' => (int) $sid,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ], $subjectIds);
                    DB::table('student_subject')->insert($rows);
                }

                $created++;
            });
        }

        return response()->json([
            'message' => "Import completed. Imported: {$created}, Skipped: {$skipped}.",
            'data' => [
                'imported' => $created,
                'skipped' => $skipped,
                'errors' => array_slice($errors, 0, 50),
            ],
        ]);
    }

    public function index(Request $request)
    {
        $query = DB::table('users')
            ->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->leftJoin('classes', 'classes.id', '=', 'student_profiles.current_class_id')
            ->where('users.role', 'student')
            ->select([
                'users.id',
                'users.admission_number',
                'users.status',
                'users.restrictions',
                'users.restriction_reason',
                'student_profiles.first_name',
                'student_profiles.last_name',
                'student_profiles.middle_name',
                'student_profiles.gender',
                'student_profiles.date_of_birth',
                'student_profiles.email',
                'student_profiles.phone',
                'student_profiles.address',
                'student_profiles.current_class_id',
                'classes.name as class_name',
            ])
            ->orderBy('student_profiles.last_name');

        if ($request->filled('class_id')) {
            $query->where('student_profiles.current_class_id', (int) $request->query('class_id'));
        }

        if ($request->filled('q')) {
            $q = '%'.strtolower((string) $request->query('q')).'%';
            $query->where(function ($w) use ($q) {
                $w->whereRaw('lower(student_profiles.first_name) like ?', [$q])
                    ->orWhereRaw('lower(student_profiles.last_name) like ?', [$q])
                    ->orWhereRaw('lower(users.admission_number) like ?', [$q]);
            });
        }

        return response()->json([
            'data' => $query->limit(200)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'admission_number' => ['required', 'string', 'max:255', 'unique:users,admission_number'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'subject_ids' => ['array'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],
            'password' => ['nullable', 'string', 'min:4', 'max:255'],
        ]);

        $password = $data['password'] ?? 'password';

        $user = User::create([
            'name' => trim($data['first_name'].' '.$data['last_name']),
            'admission_number' => $data['admission_number'],
            'role' => 'student',
            'status' => 'active',
            'password' => Hash::make($password),
        ]);

        DB::table('student_profiles')->insert([
            'user_id' => $user->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'current_class_id' => (int) $data['class_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! empty($data['subject_ids'])) {
            $rows = array_map(fn ($sid) => [
                'student_id' => $user->id,
                'subject_id' => (int) $sid,
                'created_at' => now(),
                'updated_at' => now(),
            ], $data['subject_ids']);

            DB::table('student_subject')->insert($rows);
        }

        return response()->json(['data' => ['id' => $user->id]], 201);
    }

    public function update(Request $request, int $id)
    {
        $user = User::query()->where('role', 'student')->findOrFail($id);

        $data = $request->validate([
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'subject_ids' => ['array'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],
            'status' => ['nullable', 'in:active,restricted,disabled'],
            'restrictions' => ['array'],
            'restrictions.*' => ['string', 'in:login,results'],
            'restriction_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        if (isset($data['status'])) {
            $user->update(['status' => $data['status']]);
        }

        if (array_key_exists('restrictions', $data) || array_key_exists('restriction_reason', $data)) {
            $restrictions = $data['restrictions'] ?? ($user->restrictions ?? []);
            $reason = $data['restriction_reason'] ?? $user->restriction_reason;

            // If restrictions are set, require a reason.
            if (! empty($restrictions) && (! is_string($reason) || trim($reason) === '')) {
                return response()->json([
                    'message' => 'Validation error.',
                    'errors' => [
                        'restriction_reason' => ['Reason is required when restrictions are selected.'],
                    ],
                ], 422);
            }

            // If no restrictions, clear reason.
            if (empty($restrictions)) {
                $reason = null;
            }

            $user->update([
                'restrictions' => $restrictions,
                'restriction_reason' => $reason,
            ]);
        }

        $profileUpdate = array_intersect_key($data, array_flip([
            'first_name', 'last_name', 'middle_name', 'date_of_birth', 'gender', 'email', 'phone', 'address',
        ]));

        if (isset($data['class_id'])) {
            $profileUpdate['current_class_id'] = (int) $data['class_id'];
        }

        if (! empty($profileUpdate)) {
            $profileUpdate['updated_at'] = now();
            DB::table('student_profiles')->where('user_id', $user->id)->update($profileUpdate);
        }

        if (array_key_exists('subject_ids', $data)) {
            DB::table('student_subject')->where('student_id', $user->id)->delete();
            if (! empty($data['subject_ids'])) {
                $rows = array_map(fn ($sid) => [
                    'student_id' => $user->id,
                    'subject_id' => (int) $sid,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $data['subject_ids']);
                DB::table('student_subject')->insert($rows);
            }
        }

        return response()->json(['message' => 'Student updated.']);
    }

    public function destroy(int $id)
    {
        $user = User::query()->where('role', 'student')->findOrFail($id);
        $user->update(['status' => 'disabled']);

        return response()->json(['message' => 'Student disabled (soft delete).']);
    }

    public function export(Request $request)
    {
        $classId = $request->query('class_id');
        $classId = $classId ? (int) $classId : null;

        return Excel::download(new StudentsExport($classId), 'students.xlsx');
    }
}


