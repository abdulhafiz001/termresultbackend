<?php

namespace App\Http\Controllers\Tenant\Teacher;

use App\Http\Controllers\Controller;
use App\Imports\StudentsImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;

class StudentsController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
        ]);

        $teacherId = $request->user()->id;
        $classId = (int) $data['class_id'];
        $subjectId = isset($data['subject_id']) ? (int) $data['subject_id'] : null;

        // Allow form teacher to access their form class even if they are not assigned to teach it.
        $isFormTeacherOfClass = DB::table('classes')
            ->where('id', $classId)
            ->where('form_teacher_id', $teacherId)
            ->exists();

        // If subject_id is provided, verify teacher teaches this subject in this class
        if ($subjectId) {
            $isAssigned = DB::table('teacher_class')
                ->join('teacher_subject', function ($join) use ($teacherId, $subjectId) {
                    $join->on('teacher_class.teacher_id', '=', 'teacher_subject.teacher_id')
                         ->where('teacher_subject.teacher_id', $teacherId)
                         ->where('teacher_subject.subject_id', $subjectId);
                })
                ->where('teacher_class.class_id', $classId)
                ->where('teacher_class.teacher_id', $teacherId)
                ->exists();

            if (! $isAssigned) {
                return response()->json(['message' => 'You are not assigned to teach this subject in this class.'], 403);
            }
        } else {
            // For general class access, verify teacher teaches this class
            $teachesClass = DB::table('teacher_class')
                ->where('class_id', $classId)
                ->where('teacher_id', $teacherId)
                ->exists();

            if (! $teachesClass && ! $isFormTeacherOfClass) {
                return response()->json(['message' => 'You are not assigned to teach this class.'], 403);
            }
        }

        $query = DB::table('users')
            ->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->leftJoin('classes', 'classes.id', '=', 'student_profiles.current_class_id')
            ->where('users.role', 'student')
            ->where('student_profiles.current_class_id', $classId);

        // If subject_id is provided, filter students offering that subject
        if ($subjectId) {
            $query->join('student_subject', function ($join) use ($subjectId) {
                $join->on('student_subject.student_id', '=', 'users.id')
                     ->where('student_subject.subject_id', $subjectId);
            });
        }

        $students = $query->select([
                'users.id',
                'users.admission_number',
                'users.status',
                'student_profiles.first_name',
                'student_profiles.last_name',
                'student_profiles.middle_name',
                'student_profiles.email',
                'student_profiles.phone',
                'student_profiles.address',
                'student_profiles.date_of_birth',
                'student_profiles.gender',
                'student_profiles.current_class_id',
                'classes.name as class_name',
            ])
            ->distinct()
            ->orderBy('student_profiles.last_name')
            ->get();

        // Get subject IDs for each student
        $studentIds = $students->pluck('id')->toArray();
        $subjectAssignments = [];
        if (!empty($studentIds)) {
            $subjectRows = DB::table('student_subject')
                ->join('subjects', 'subjects.id', '=', 'student_subject.subject_id')
                ->whereIn('student_subject.student_id', $studentIds)
                ->select(['student_subject.student_id', 'subjects.id as subject_id', 'subjects.name as subject_name'])
                ->get();
            
            foreach ($subjectRows as $row) {
                if (!isset($subjectAssignments[$row->student_id])) {
                    $subjectAssignments[$row->student_id] = [];
                }
                $subjectAssignments[$row->student_id][] = [
                    'id' => $row->subject_id,
                    'name' => $row->subject_name,
                ];
            }
        }

        $students = $students->map(function ($student) use ($subjectAssignments) {
            $studentArray = (array) $student;
            $studentArray['subject_ids'] = collect($subjectAssignments[$student->id] ?? [])->pluck('id')->toArray();
            $studentArray['subjects'] = $subjectAssignments[$student->id] ?? [];
            return $studentArray;
        });

        return response()->json(['data' => $students]);
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
        ]);

        $teacherId = $request->user()->id;

        // Only allow a form teacher to add students to their form class.
        $isFormTeacher = DB::table('classes')
            ->where('id', (int) $data['class_id'])
            ->where('form_teacher_id', $teacherId)
            ->exists();

        if (! $isFormTeacher) {
            return response()->json(['message' => 'Only the form teacher can add students to this class.'], 403);
        }

        // Teachers cannot set student passwords; default is always "password".
        $password = 'password';

        $userId = DB::table('users')->insertGetId([
            'name' => trim($data['first_name'].' '.$data['last_name']),
            'admission_number' => $data['admission_number'],
            'role' => 'student',
            'status' => 'active',
            'password' => bcrypt($password),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('student_profiles')->insert([
            'user_id' => $userId,
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
                'student_id' => $userId,
                'subject_id' => (int) $sid,
                'created_at' => now(),
                'updated_at' => now(),
            ], $data['subject_ids']);
            DB::table('student_subject')->insert($rows);
        }

        return response()->json([
            'data' => [
                'id' => $userId,
            ],
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $teacherId = $request->user()->id;

        // Get student's current class
        $studentProfile = DB::table('student_profiles')
            ->where('user_id', $id)
            ->first();

        if (! $studentProfile) {
            return response()->json(['message' => 'Student not found.'], 404);
        }

        // Only allow form teacher to update students in their form class
        $isFormTeacher = DB::table('classes')
            ->where('id', $studentProfile->current_class_id)
            ->where('form_teacher_id', $teacherId)
            ->exists();

        if (! $isFormTeacher) {
            return response()->json(['message' => 'You can only edit students in your form class.'], 403);
        }

        $data = $request->validate([
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female'],
            'class_id' => ['sometimes', 'integer', 'exists:classes,id'],
            'subject_ids' => ['array'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],
        ]);

        // If updating class, verify teacher is form teacher of new class
        if (isset($data['class_id']) && $data['class_id'] != $studentProfile->current_class_id) {
            $isFormTeacherOfNewClass = DB::table('classes')
                ->where('id', (int) $data['class_id'])
                ->where('form_teacher_id', $teacherId)
                ->exists();

            if (! $isFormTeacherOfNewClass) {
                return response()->json(['message' => 'You can only assign students to your form class.'], 403);
            }
        }

        // Teachers cannot change student passwords.

        // Update student profile
        $profileUpdate = [];
        if (isset($data['first_name'])) $profileUpdate['first_name'] = $data['first_name'];
        if (isset($data['last_name'])) $profileUpdate['last_name'] = $data['last_name'];
        if (array_key_exists('middle_name', $data)) $profileUpdate['middle_name'] = $data['middle_name'];
        if (array_key_exists('email', $data)) $profileUpdate['email'] = $data['email'];
        if (array_key_exists('phone', $data)) $profileUpdate['phone'] = $data['phone'];
        if (array_key_exists('address', $data)) $profileUpdate['address'] = $data['address'];
        if (array_key_exists('date_of_birth', $data)) $profileUpdate['date_of_birth'] = $data['date_of_birth'];
        if (array_key_exists('gender', $data)) $profileUpdate['gender'] = $data['gender'];
        if (isset($data['class_id'])) $profileUpdate['current_class_id'] = (int) $data['class_id'];
        $profileUpdate['updated_at'] = now();

        if (! empty($profileUpdate)) {
            DB::table('student_profiles')
                ->where('user_id', $id)
                ->update($profileUpdate);
        }

        // Update user name
        if (isset($data['first_name']) || isset($data['last_name'])) {
            $firstName = $data['first_name'] ?? ($studentProfile->first_name ?? '');
            $lastName = $data['last_name'] ?? ($studentProfile->last_name ?? '');
            if ($firstName || $lastName) {
                DB::table('users')
                    ->where('id', $id)
                    ->update([
                        'name' => trim($firstName.' '.$lastName),
                        'updated_at' => now(),
                    ]);
            }
        }

        // Update subjects if provided
        if (array_key_exists('subject_ids', $data)) {
            DB::table('student_subject')->where('student_id', $id)->delete();
            if (! empty($data['subject_ids'])) {
                $rows = array_map(fn ($sid) => [
                    'student_id' => $id,
                    'subject_id' => (int) $sid,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $data['subject_ids']);
                DB::table('student_subject')->insert($rows);
            }
        }

        return response()->json(['message' => 'Student updated successfully.']);
    }

    public function import(Request $request)
    {
        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'file' => ['required', 'file', 'max:5120'], // 5MB
            'subject_ids' => ['nullable', 'string'], // JSON string array
        ]);

        $teacherId = $request->user()->id;
        $classId = (int) $data['class_id'];

        // Only allow form teacher to import students
        $isFormTeacher = DB::table('classes')
            ->where('id', $classId)
            ->where('form_teacher_id', $teacherId)
            ->exists();

        if (! $isFormTeacher) {
            return response()->json(['message' => 'Only the form teacher can import students to this class.'], 403);
        }

        $subjectIds = [];
        if (!empty($data['subject_ids'])) {
            $decoded = json_decode($data['subject_ids'], true);
            if (is_array($decoded)) {
                $subjectIds = array_map('intval', $decoded);
            }
        }

        $file = $request->file('file');
        
        // Use the same import class as admin
        $sheets = Excel::toArray(new StudentsImport(), $file);
        $rows = $sheets[0] ?? [];

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            $first = trim((string) ($row['first_name'] ?? ''));
            $last = trim((string) ($row['last_name'] ?? ''));
            $middle = trim((string) ($row['middle_name'] ?? '')) ?: null;
            $admission = trim((string) ($row['admission_number'] ?? ''));
            $email = trim((string) ($row['email'] ?? '')) ?: null;
            $phone = trim((string) ($row['phone'] ?? '')) ?: null;
            $dob = trim((string) ($row['date_of_birth'] ?? '')) ?: null;
            $gender = strtolower(trim((string) ($row['gender'] ?? ''))) ?: null;
            $address = trim((string) ($row['address'] ?? '')) ?: null;

            $rowNum = $i + 2;

            if ($first === '' || $last === '' || $admission === '') {
                $errors[] = "Row {$rowNum}: Missing required fields (first_name, last_name, admission_number)";
                $skipped++;
                continue;
            }

            // Check if admission number already exists
            $existing = DB::table('users')->where('admission_number', $admission)->first();
            if ($existing) {
                $errors[] = "Row {$rowNum}: Admission number {$admission} already exists";
                $skipped++;
                continue;
            }

            try {
                $password = 'password';
                $userId = DB::table('users')->insertGetId([
                    'name' => trim($first . ' ' . $last),
                    'admission_number' => $admission,
                    'role' => 'student',
                    'status' => 'active',
                    'password' => Hash::make($password),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('student_profiles')->insert([
                    'user_id' => $userId,
                    'first_name' => $first,
                    'last_name' => $last,
                    'middle_name' => $middle,
                    'email' => $email,
                    'phone' => $phone,
                    'date_of_birth' => $dob ?: null,
                    'gender' => in_array($gender, ['male', 'female']) ? $gender : null,
                    'address' => $address,
                    'current_class_id' => $classId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Assign subjects if provided
                if (!empty($subjectIds)) {
                    $subjectRows = array_map(fn ($sid) => [
                        'student_id' => $userId,
                        'subject_id' => (int) $sid,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ], $subjectIds);
                    DB::table('student_subject')->insert($subjectRows);
                }

                $created++;
            } catch (\Exception $e) {
                $errors[] = "Row {$rowNum}: " . $e->getMessage();
                $skipped++;
            }
        }

        return response()->json([
            'message' => "Import completed. Created: {$created}, Skipped: {$skipped}",
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }
}


