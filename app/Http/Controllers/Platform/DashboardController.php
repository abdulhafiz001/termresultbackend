<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
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

            $activeSchools = School::query()->where('status', 'active')->whereNotNull('database_name')->get(['id', 'database_name', 'subdomain']);
            $central = app()->bound('central.connection') ? app('central.connection') : config('database.default');

            foreach ($activeSchools as $school) {
                try {
                    Config::set('database.connections.tenant.database', $school->database_name);
                    DB::purge('tenant');

                    $tenant = DB::connection('tenant');

                    if ($tenant->getSchemaBuilder()->hasTable('users')) {
                        $studentTotal += (int) $tenant->table('users')->where('role', 'student')->count();
                        $teacherTotal += (int) $tenant->table('users')->where('role', 'teacher')->count();
                        $adminTotal += (int) $tenant->table('users')->where('role', 'school_admin')->count();
                    }

                    if ($tenant->getSchemaBuilder()->hasTable('payments') && $tenant->getSchemaBuilder()->hasColumn('payments', 'service_fee_kobo')) {
                        $serviceFeeTotalKobo += (int) $tenant->table('payments')->where('status', 'success')->sum('service_fee_kobo');
                    }
                } catch (\Throwable $e) {
                    // Skip broken tenant; keep platform usable.
                    try {
                        // Guard for fresh installs where audit_logs migration hasn't been run yet.
                        if (Schema::connection($central)->hasTable('audit_logs')) {
                            DB::connection($central)->table('audit_logs')->insert([
                                'action' => 'platform_stats_tenant_error',
                                'subject_type' => School::class,
                                'subject_id' => $school->id,
                                'metadata' => json_encode(['error' => $e->getMessage()]),
                                'ip' => null,
                                'user_agent' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    } catch (\Throwable $ignored) {
                        // ignore
                    }
                }
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


