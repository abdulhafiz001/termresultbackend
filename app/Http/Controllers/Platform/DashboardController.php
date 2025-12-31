<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        // Cache because cross-tenant aggregation can be expensive.
        $cacheKey = 'tr:platform:dashboard:stats';

        return Cache::remember($cacheKey, 60, function () {
            $schoolsTotal = (int) School::query()->count();
            $schoolsPending = (int) School::query()->where('status', 'pending')->count();
            $schoolsActive = (int) School::query()->where('status', 'active')->count();
            $schoolsDeclined = (int) School::query()->where('status', 'declined')->count();

            // Cross-tenant aggregation (best-effort).
            $studentTotal = 0;
            $teacherTotal = 0;
            $adminTotal = 0;
            $serviceFeeTotalKobo = 0;

            // Single-database tenancy: aggregate directly from central tables, filtering to active schools.
            $activeTenantIds = School::query()
                ->where('status', 'active')
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->values()
                ->all();

            if (! empty($activeTenantIds) && Schema::hasTable('users')) {
                $studentTotal = (int) DB::table('users')->whereIn('tenant_id', $activeTenantIds)->where('role', 'student')->count();
                $teacherTotal = (int) DB::table('users')->whereIn('tenant_id', $activeTenantIds)->where('role', 'teacher')->count();
                $adminTotal = (int) DB::table('users')->whereIn('tenant_id', $activeTenantIds)->where('role', 'school_admin')->count();
            }

            if (! empty($activeTenantIds) && Schema::hasTable('payments') && Schema::hasColumn('payments', 'service_fee_kobo')) {
                $serviceFeeTotalKobo = (int) DB::table('payments')
                    ->whereIn('tenant_id', $activeTenantIds)
                    ->where('status', 'success')
                    ->sum('service_fee_kobo');
            }

            return response()->json([
                'data' => [
                    'schools' => [
                        'total' => $schoolsTotal,
                        'active' => $schoolsActive,
                        'pending' => $schoolsPending,
                        'declined' => $schoolsDeclined,
                    ],
                    'users' => [
                        'students' => $studentTotal,
                        'teachers' => $teacherTotal,
                        'admins' => $adminTotal,
                    ],
                    'service_fees' => [
                        'total_kobo' => $serviceFeeTotalKobo,
                    ],
                ],
            ]);
        });
    }
}


