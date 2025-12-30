<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Support\TenantCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SubjectsController extends Controller
{
    public function index()
    {
        $school = app('tenant.school');
        $cacheKey = TenantCache::adminSubjectsKey((int) $school->id);

        $subjects = Cache::remember($cacheKey, now()->addMinutes(10), function () {
            return Subject::query()->orderBy('name')->get();
        });

        return response()->json([
            'data' => $subjects,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:subjects,name'],
            'code' => ['nullable', 'string', 'max:50', 'unique:subjects,code'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $subject = Subject::create($data);
        TenantCache::forgetAdminLists(app('tenant.school'));

        return response()->json(['data' => $subject], 201);
    }

    public function update(Request $request, int $id)
    {
        $subject = Subject::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', 'unique:subjects,name,'.$subject->id],
            'code' => ['nullable', 'string', 'max:50', 'unique:subjects,code,'.$subject->id],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $subject->update($data);
        TenantCache::forgetAdminLists(app('tenant.school'));

        return response()->json(['data' => $subject]);
    }

    public function destroy(int $id)
    {
        $subject = Subject::findOrFail($id);
        $subject->delete();
        TenantCache::forgetAdminLists(app('tenant.school'));

        return response()->json(['message' => 'Subject deleted.']);
    }
}


