<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TrafficController extends Controller
{
    public function daily(Request $request)
    {
        $data = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $days = (int) ($data['days'] ?? 14);
        $cacheKey = "tr:platform:traffic:daily:days={$days}";

        return Cache::remember($cacheKey, 60, function () use ($days) {
            $central = app()->bound('central.connection') ? app('central.connection') : config('database.default');

            $rows = DB::connection($central)
                ->table('platform_traffic_events')
                ->select([
                    DB::raw('date(created_at) as day'),
                    DB::raw('count(*) as cnt'),
                ])
                ->where('created_at', '>=', now()->subDays($days))
                ->groupBy(DB::raw('date(created_at)'))
                ->orderBy(DB::raw('date(created_at)'))
                ->get();

            return response()->json(['data' => $rows]);
        });
    }

    public function topSchools(Request $request)
    {
        $data = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:60'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $days = (int) ($data['days'] ?? 14);
        $limit = (int) ($data['limit'] ?? 10);
        $cacheKey = "tr:platform:traffic:top_schools:days={$days}:limit={$limit}";

        return Cache::remember($cacheKey, 60, function () use ($days, $limit) {
            $central = app()->bound('central.connection') ? app('central.connection') : config('database.default');

            $rows = DB::connection($central)
                ->table('platform_traffic_events as e')
                ->leftJoin('schools as s', 's.id', '=', 'e.school_id')
                ->select([
                    'e.school_id',
                    's.name as school_name',
                    's.subdomain',
                    DB::raw('count(*) as cnt'),
                ])
                ->whereNotNull('e.school_id')
                ->where('e.created_at', '>=', now()->subDays($days))
                ->groupBy('e.school_id', 's.name', 's.subdomain')
                ->orderByDesc(DB::raw('count(*)'))
                ->limit($limit)
                ->get();

            return response()->json(['data' => $rows]);
        });
    }
}


