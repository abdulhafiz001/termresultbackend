<?php

namespace App\Http\Controllers\Tenant\Teacher;

use App\Http\Controllers\Controller;
use App\Support\TenantCache;
use App\Support\TenantDB;
use Illuminate\Support\Facades\Cache;

class AcademicController extends Controller
{
    public function sessions()
    {
        $school = app('tenant.school');
        $cacheKey = TenantCache::academicSessionsKey((int) $school->id);

        $sessions = Cache::remember($cacheKey, now()->addMinutes(30), function () {
            return TenantDB::table('academic_sessions')
                ->orderByDesc('is_current')
                ->orderByDesc('id')
                ->get()
                ->map(function ($s) {
                    $terms = TenantDB::table('terms')
                        ->where('academic_session_id', $s->id)
                        ->orderByDesc('is_current')
                        ->orderBy('id')
                        ->get(['id', 'name', 'is_current']);

                    return [
                        'id' => $s->id,
                        'name' => $s->name,
                        'is_current' => (bool) $s->is_current,
                        'terms' => $terms->map(fn ($t) => [
                            'id' => $t->id,
                            'name' => $t->name,
                            'is_current' => (bool) $t->is_current,
                        ]),
                    ];
                });
        });

        return response()->json(['data' => $sessions]);
    }
}


