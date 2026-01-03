<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Imports\SubjectsImport;
use App\Models\Subject;
use App\Support\TenantCache;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class SubjectsController extends Controller
{
    public function index()
    {
        $school = app('tenant.school');
        $cacheKey = TenantCache::adminSubjectsKey((int) $school->id);
        $tenantId = TenantContext::id();

        $subjects = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($tenantId) {
            return Subject::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get();
        });

        return response()->json([
            'data' => $subjects,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('subjects', 'name')->where('tenant_id', $tenantId),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('subjects', 'code')->where('tenant_id', $tenantId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $subject = Subject::create(TenantContext::withTenant($data));
        TenantCache::forgetAdminLists(app('tenant.school'));

        return response()->json(['data' => $subject], 201);
    }

    public function update(Request $request, int $id)
    {
        $tenantId = TenantContext::id();
        $subject = Subject::query()->where('tenant_id', $tenantId)->findOrFail($id);

        $data = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('subjects', 'name')
                    ->ignore($subject->id)
                    ->where('tenant_id', $tenantId),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('subjects', 'code')
                    ->ignore($subject->id)
                    ->where('tenant_id', $tenantId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $subject->update($data);
        TenantCache::forgetAdminLists(app('tenant.school'));

        return response()->json(['data' => $subject]);
    }

    public function destroy(int $id)
    {
        $tenantId = TenantContext::id();
        $subject = Subject::query()->where('tenant_id', $tenantId)->findOrFail($id);
        $subject->delete();
        TenantCache::forgetAdminLists(app('tenant.school'));

        return response()->json(['message' => 'Subject deleted.']);
    }

    public function import(Request $request)
    {
        $tenantId = TenantContext::id();

        $data = $request->validate([
            'file' => ['required', 'file', 'max:5120'], // 5MB
        ]);

        $file = $request->file('file');
        $sheets = Excel::toArray(new SubjectsImport(), $file);
        $rows = $sheets[0] ?? [];

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $code = trim((string) ($row['code'] ?? '')) ?: null;
            $description = trim((string) ($row['description'] ?? '')) ?: null;

            $rowNum = $i + 2; // heading row + 1-indexed

            if ($name === '') {
                $skipped++;
                $errors[] = "Row {$rowNum}: name is required.";
                continue;
            }

            // Skip duplicates within this tenant.
            $exists = Subject::query()
                ->where('tenant_id', $tenantId)
                ->where('name', $name)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            if ($code !== null) {
                $codeExists = Subject::query()
                    ->where('tenant_id', $tenantId)
                    ->where('code', $code)
                    ->exists();
                if ($codeExists) {
                    $skipped++;
                    $errors[] = "Row {$rowNum}: code '{$code}' already exists.";
                    continue;
                }
            }

            Subject::create([
                'tenant_id' => $tenantId,
                'name' => $name,
                'code' => $code,
                'description' => $description,
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


