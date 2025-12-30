<?php

namespace App\Http\Middleware;

use App\Models\School;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class IdentifyTenant
{
    public function handle(Request $request, Closure $next)
    {
        // Preserve the original (central) default connection so tenant routes can still write to platform tables if needed.
        if (! app()->bound('central.connection')) {
            app()->instance('central.connection', DB::getDefaultConnection());
        }

        $centralConnection = app('central.connection');

        $subdomain = $this->resolveSubdomain($request);

        if (! $subdomain) {
            // Ensure the default connection is restored for non-tenant requests too.
            DB::setDefaultConnection($centralConnection);
            return $next($request);
        }

        $school = School::query()
            ->where('subdomain', $subdomain)
            ->where('status', 'active')
            ->first();

        if (! $school || ! $school->database_name) {
            DB::setDefaultConnection($centralConnection);
            return $next($request);
        }

        // If the entire school site is restricted, block tenant API access (but still allow public tenant resolve).
        // Frontend will show a notice using `/public/tenants/resolve`.
        $restrictions = $school->restrictions ?? [];
        if (is_string($restrictions)) {
            $restrictions = json_decode($restrictions, true) ?? [];
        }
        $siteRestricted = (bool) ($restrictions['site_restricted'] ?? false);
        if ($siteRestricted) {
            // Allow tenant resolve + other public endpoints to work so the UI can show the restriction notice.
            if ($request->is('api/tenant/*') || $request->is('tenant/*')) {
                DB::setDefaultConnection($centralConnection);
                return response()->json([
                    'message' => 'This school site has been restricted by TermResult.',
                    'reason' => (string) ($restrictions['site_reason'] ?? 'Please contact TermResult support.'),
                ], 403);
            }
        }

        Config::set('database.connections.tenant.database', $school->database_name);

        // Ensure next queries use the fresh tenant database.
        DB::purge('tenant');
        DB::setDefaultConnection('tenant');

        app()->instance('tenant.school', $school);

        try {
            return $next($request);
        } finally {
            // Important: prevent tenant connection leaking into subsequent requests (same PHP process).
            DB::setDefaultConnection($centralConnection);
        }
    }

    private function resolveSubdomain(Request $request): ?string
    {
        // Useful for local dev / testing without wildcard DNS.
        $forced = $request->header('X-TermResult-Subdomain') ?: $request->query('tenant');
        if (is_string($forced) && $forced !== '') {
            return $this->normalizeSubdomain($forced);
        }

        $host = $request->getHost();

        // Ignore API / root domains.
        $host = strtolower($host);
        if ($host === 'termresult.com' || $host === 'www.termresult.com' || $host === 'api.termresult.com') {
            return null;
        }

        // Support "*.termresult.com"
        if (str_ends_with($host, '.termresult.com')) {
            $sub = substr($host, 0, -strlen('.termresult.com'));
            return $this->normalizeSubdomain($sub);
        }

        // Support "<school>.localhost" for local dev.
        if (str_ends_with($host, '.localhost')) {
            $sub = substr($host, 0, -strlen('.localhost'));
            return $this->normalizeSubdomain($sub);
        }

        return null;
    }

    private function normalizeSubdomain(string $subdomain): ?string
    {
        $subdomain = strtolower(trim($subdomain));

        // Block obvious invalid hosts.
        if ($subdomain === '' || $subdomain === 'www' || $subdomain === 'api') {
            return null;
        }

        // Only allow simple slugs.
        if (! preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $subdomain)) {
            return null;
        }

        return $subdomain;
    }
}


