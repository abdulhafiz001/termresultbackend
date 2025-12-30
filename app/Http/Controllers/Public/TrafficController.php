<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrafficController extends Controller
{
    public function store(Request $request)
    {
        // Support no-cors clients sending a raw JSON string with Content-Type text/plain (no preflight).
        // If Laravel didn't parse JSON into the request payload, try decoding the raw body and merging.
        if (! $request->has('path') && ! $request->has('referrer')) {
            $raw = (string) $request->getContent();
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $request->merge($decoded);
                }
            }
        }

        $data = $request->validate([
            'path' => ['nullable', 'string', 'max:500'],
            'referrer' => ['nullable', 'string', 'max:500'],
        ]);

        $host = strtolower((string) $request->getHost());
        $subdomain = null;
        if (str_ends_with($host, '.termresult.com')) {
            $subdomain = substr($host, 0, -strlen('.termresult.com'));
        } elseif (str_ends_with($host, '.localhost')) {
            $subdomain = substr($host, 0, -strlen('.localhost'));
        }

        $schoolId = null;
        if ($subdomain && $subdomain !== 'www' && $subdomain !== 'api') {
            $schoolId = School::query()->where('subdomain', $subdomain)->value('id');
        }

        $ip = (string) ($request->ip() ?? '');
        $ipHash = $ip ? hash('sha256', $ip) : null;

        $central = app()->bound('central.connection') ? app('central.connection') : config('database.default');
        DB::connection($central)->table('platform_traffic_events')->insert([
            'school_id' => $schoolId,
            'host' => $host ?: null,
            'subdomain' => $subdomain ?: null,
            'path' => isset($data['path']) ? (string) $data['path'] : null,
            'referrer' => isset($data['referrer']) ? (string) $data['referrer'] : null,
            'ip_hash' => $ipHash,
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }
}


