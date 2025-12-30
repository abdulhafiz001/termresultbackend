<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Support\TenantCache;

class TenantResolveController extends Controller
{
    public function resolve(Request $request)
    {
        // Prefer tenant resolved by IdentifyTenant (when available).
        // This is the most reliable path in local dev where API host is 127.0.0.1
        // but the tenant subdomain is sent via X-TermResult-Subdomain header.
        $resolved = app()->bound('tenant.school') ? app('tenant.school') : null;

        $subdomain = null;

        // Next best: explicit header sent by frontend axios interceptor.
        if (! $resolved) {
            $forced = (string) ($request->header('X-TermResult-Subdomain') ?: '');
            $forced = strtolower(trim($forced));
            if ($forced !== '' && $this->isValidSubdomain($forced)) {
                $subdomain = $forced;
            }
        }

        // Fallbacks: query param host, request host, and Origin header.
        if (! $resolved && ! $subdomain) {
            $host = strtolower((string) $request->query('host', $request->getHost()));

            $origin = (string) $request->header('Origin', '');
            if ($origin !== '') {
                $originHost = strtolower(parse_url($origin, PHP_URL_HOST) ?: '');
                if ($originHost !== '') {
                    $host = $originHost;
                }
            }

            if (str_ends_with($host, '.termresult.com')) {
                $subdomain = substr($host, 0, -strlen('.termresult.com'));
            } elseif (str_ends_with($host, '.localhost')) {
                $subdomain = substr($host, 0, -strlen('.localhost'));
            }

            if ($subdomain !== null) {
                $subdomain = strtolower(trim($subdomain));
                if (! $this->isValidSubdomain($subdomain)) {
                    $subdomain = null;
                }
            }
        }

        // When no tenant subdomain is present (e.g. localhost), return a 200 with null tenant.
        // This avoids noisy 404s in the frontend for non-tenant pages.
        if (! $resolved && ! $subdomain) {
            return response()->json([
                'tenant' => null,
                'message' => 'No tenant subdomain detected.',
            ]);
        }

        $school = $resolved;
        if (! $school) {
            $cacheKey = TenantCache::publicTenantResolveKey((string) $subdomain);
            $school = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($subdomain) {
                return School::query()
                    ->where('subdomain', $subdomain)
                    ->where('status', 'active')
                    ->first();
            });
        }

        // If subdomain is present but not found, return 200 with null tenant (frontend can handle gracefully).
        if (! $school) {
            return response()->json([
                'tenant' => null,
                'message' => 'Tenant not found.',
            ]);
        }

        $theme = $school->theme ?? [];
        if (is_string($theme)) {
            $theme = json_decode($theme, true) ?? [];
        }
        
        $logoUrl = null;
        // Check both logo_path (current) and logo_url (legacy) for backward compatibility
        if (!empty($theme['logo_path'])) {
            $logoUrl = url('storage/' . $theme['logo_path']);
        } elseif (!empty($theme['logo_url'])) {
            // Handle legacy logo_url (if it's a full URL, use it; if it's a path, prepend storage)
            $logoUrl = str_starts_with($theme['logo_url'], 'http') 
                ? $theme['logo_url'] 
                : url('storage/' . $theme['logo_url']);
        }

        $featureToggles = $school->feature_toggles ?? [];
        if (is_string($featureToggles)) {
            $featureToggles = json_decode($featureToggles, true) ?? [];
        }

        $restrictions = $school->restrictions ?? [];
        if (is_string($restrictions)) {
            $restrictions = json_decode($restrictions, true) ?? [];
        }

        return response()->json([
            'tenant' => [
                'id' => $school->id,
                'name' => $school->name,
                'subdomain' => $school->subdomain,
                'status' => $school->status,
                'theme' => $theme,
                'logo_url' => $logoUrl,
                'feature_toggles' => $featureToggles,
                'restrictions' => $restrictions,
            ],
        ]);
    }

    private function isValidSubdomain(string $subdomain): bool
    {
        if ($subdomain === '' || $subdomain === 'www' || $subdomain === 'api') {
            return false;
        }

        return (bool) preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $subdomain);
    }
}


