<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeachersController extends Controller
{
    public function index()
    {
        $teachers = User::query()
            ->where('role', 'teacher')
            ->orderBy('name')
            ->get()
            ->map(function ($t) {
                $classIds = TenantDB::table('teacher_class')->where('teacher_id', $t->id)->pluck('class_id');
                $subjectIds = TenantDB::table('teacher_subject')->where('teacher_id', $t->id)->pluck('subject_id');

                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'username' => $t->username,
                    'email' => $t->email,
                    'phone' => $t->phone,
                    'status' => $t->status,
                    'class_ids' => $classIds,
                    'subject_ids' => $subjectIds,
                ];
            });

        return response()->json(['data' => $teachers]);
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('users', 'username')->where('tenant_id', $tenantId),
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->where('tenant_id', $tenantId),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['nullable', 'string', 'min:4', 'max:255'],
            'class_ids' => ['array'],
            'class_ids.*' => ['integer', 'exists:classes,id'],
            'subject_ids' => ['array'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],
        ]);

        $username = $data['username'] ?? $this->generateUsernameFromName($data['name']);
        $password = $data['password'] ?? 'password';

        $teacher = User::create([
            'name' => $data['name'],
            'username' => $username,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'role' => 'teacher',
            'status' => 'active',
            'password' => Hash::make($password),
        ]);

        $this->syncTeacherAssignments($teacher->id, $data['class_ids'] ?? [], $data['subject_ids'] ?? []);

        return response()->json([
            'data' => [
                'id' => $teacher->id,
                'username' => $teacher->username,
                'default_password' => $data['password'] ? null : $password,
            ],
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $teacher = User::query()->where('role', 'teacher')->findOrFail($id);
        $tenantId = TenantContext::id();

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($teacher->id)->where('tenant_id', $tenantId),
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($teacher->id)->where('tenant_id', $tenantId),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'in:active,restricted,disabled'],
            'class_ids' => ['array'],
            'class_ids.*' => ['integer', 'exists:classes,id'],
            'subject_ids' => ['array'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],
        ]);

        $teacher->update(array_intersect_key($data, array_flip(['name', 'username', 'email', 'phone', 'status'])));

        if (array_key_exists('class_ids', $data) || array_key_exists('subject_ids', $data)) {
            $this->syncTeacherAssignments($teacher->id, $data['class_ids'] ?? [], $data['subject_ids'] ?? []);
        }

        return response()->json(['message' => 'Teacher updated.']);
    }

    public function destroy(int $id)
    {
        $teacher = User::query()->where('role', 'teacher')->findOrFail($id);
        $teacher->update(['status' => 'disabled']);

        return response()->json(['message' => 'Teacher disabled (soft delete).']);
    }

    private function generateUsernameFromName(string $name): string
    {
        $teacherSlug = Str::slug($name, '.');
        $school = app()->bound('tenant.school') ? app('tenant.school') : null;
        $schoolSlug = $school && $school->subdomain ? Str::slug($school->subdomain, '.') : 'school';

        $base = trim($teacherSlug . '.' . $schoolSlug, '.');
        if ($base === '') {
            $base = 'teacher.' . $schoolSlug;
        }

        $candidate = $base;

        $i = 1;
        while (User::query()->where('username', $candidate)->exists()) {
            $i++;
            $candidate = $base.'.'.$i;
        }

        return $candidate;
    }

    private function syncTeacherAssignments(int $teacherId, array $classIds, array $subjectIds): void
    {
        $tenantId = TenantContext::id();

        TenantDB::table('teacher_class')->where('teacher_id', $teacherId)->delete();
        TenantDB::table('teacher_subject')->where('teacher_id', $teacherId)->delete();

        if (! empty($classIds)) {
            $rows = array_map(fn ($cid) => [
                'tenant_id' => $tenantId,
                'teacher_id' => $teacherId,
                'class_id' => (int) $cid,
                'created_at' => now(),
                'updated_at' => now(),
            ], $classIds);
            DB::table('teacher_class')->insert($rows);
        }

        if (! empty($subjectIds)) {
            $rows = array_map(fn ($sid) => [
                'tenant_id' => $tenantId,
                'teacher_id' => $teacherId,
                'subject_id' => (int) $sid,
                'created_at' => now(),
                'updated_at' => now(),
            ], $subjectIds);
            DB::table('teacher_subject')->insert($rows);
        }
    }
}


