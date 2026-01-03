<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Imports\ClassesImport;
use App\Models\SchoolClass;
use App\Support\TenantCache;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Validation\Rule;

class ClassesController extends Controller
{
    public function index()
    {
        $school = app('tenant.school');
        $cacheKey = TenantCache::adminClassesKey((int) $school->id);
        $tenantId = TenantContext::id();

        $classes = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($tenantId) {
            return SchoolClass::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get()
                ->map(function ($c) {
                    $studentCount = TenantDB::table('student_profiles')
                        ->where('current_class_id', $c->id)
                        ->count();

                    return [
                        'id' => $c->id,
                        'name' => $c->name,
                        'description' => $c->description,
                        'form_teacher_id' => $c->form_teacher_id,
                        'student_count' => $studentCount,
                    ];
                });
        });

        return response()->json(['data' => $classes]);
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('classes', 'name')->where('tenant_id', $tenantId),
            ],
            'form_teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        // Ensure tenant_id is always set.
        $class = SchoolClass::create(TenantContext::withTenant($data));
        TenantCache::forgetAdminLists(app('tenant.school'));

        return response()->json(['data' => $class], 201);
    }

    public function update(Request $request, int $id)
    {
        $tenantId = TenantContext::id();
        $class = SchoolClass::query()->where('tenant_id', $tenantId)->findOrFail($id);

        $data = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('classes', 'name')
                    ->ignore($class->id)
                    ->where('tenant_id', $tenantId),
            ],
            'form_teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $class->update($data);
        TenantCache::forgetAdminLists(app('tenant.school'));

        return response()->json(['data' => $class]);
    }

    public function destroy(int $id)
    {
        $tenantId = TenantContext::id();
        $class = SchoolClass::query()->where('tenant_id', $tenantId)->findOrFail($id);
        $class->delete();
        TenantCache::forgetAdminLists(app('tenant.school'));

        return response()->json(['message' => 'Class deleted.']);
    }

    public function import(Request $request)
    {
        $tenantId = TenantContext::id();

        $data = $request->validate([
            'file' => ['required', 'file', 'max:5120'], // 5MB
        ]);

        $file = $request->file('file');
        $sheets = Excel::toArray(new ClassesImport(), $file);
        $rows = $sheets[0] ?? [];

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $description = trim((string) ($row['description'] ?? '')) ?: null;
            $formTeacherUsername = trim((string) ($row['form_teacher_username'] ?? '')) ?: null;

            $rowNum = $i + 2;

            if ($name === '') {
                $skipped++;
                $errors[] = "Row {$rowNum}: name is required.";
                continue;
            }

            // Skip duplicates within this tenant.
            $exists = SchoolClass::query()
                ->where('tenant_id', $tenantId)
                ->where('name', $name)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $formTeacherId = null;
            if ($formTeacherUsername) {
                $formTeacherId = TenantDB::table('users')
                    ->where('role', 'teacher')
                    ->where('username', $formTeacherUsername)
                    ->value('id');

                if (! $formTeacherId) {
                    $skipped++;
                    $errors[] = "Row {$rowNum}: teacher username '{$formTeacherUsername}' not found.";
                    continue;
                }
            }

            SchoolClass::create([
                'tenant_id' => $tenantId,
                'name' => $name,
                'description' => $description,
                'form_teacher_id' => $formTeacherId,
            ]);

            $created++;
        }

        TenantCache::forgetAdminLists(app('tenant.school'));

        return response()->json([
            'message' => "Import completed. Imported: {$created}, Skipped: {$skipped}.",
            'data' => [
                'imported' => $created,
                'skipped' => $skipped,
                'errors' => array_slice($errors, 0, 50),
            ],
        ]);
    }
}


