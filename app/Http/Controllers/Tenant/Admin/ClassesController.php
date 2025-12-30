<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Support\TenantCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ClassesController extends Controller
{
    public function index()
    {
        $school = app('tenant.school');
        $cacheKey = TenantCache::adminClassesKey((int) $school->id);

        $classes = Cache::remember($cacheKey, now()->addMinutes(10), function () {
            return SchoolClass::query()
                ->orderBy('name')
                ->get()
                ->map(function ($c) {
                    $studentCount = DB::table('student_profiles')
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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:classes,name'],
            'form_teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $class = SchoolClass::create($data);
        TenantCache::forgetAdminLists(app('tenant.school'));

        return response()->json(['data' => $class], 201);
    }

    public function update(Request $request, int $id)
    {
        $class = SchoolClass::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', 'unique:classes,name,'.$class->id],
            'form_teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $class->update($data);
        TenantCache::forgetAdminLists(app('tenant.school'));

        return response()->json(['data' => $class]);
    }

    public function destroy(int $id)
    {
        $class = SchoolClass::findOrFail($id);
        $class->delete();
        TenantCache::forgetAdminLists(app('tenant.school'));

        return response()->json(['message' => 'Class deleted.']);
    }
}


