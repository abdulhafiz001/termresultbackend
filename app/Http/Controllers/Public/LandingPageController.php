<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Support\TenantCache;

class LandingPageController extends Controller
{
    public function getContent(Request $request)
    {
        // IMPORTANT: Do not call app('tenant.school') unless it's bound; otherwise Laravel tries to resolve it as a class.
        $school = app()->bound('tenant.school') ? app('tenant.school') : null;

        // Fallback: resolve school from header/host (same idea as TenantResolveController).
        if (! $school) {
            $subdomain = null;
            $forced = strtolower(trim((string) ($request->header('X-TermResult-Subdomain') ?: '')));
            if ($forced !== '' && preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $forced)) {
                $subdomain = $forced;
            } else {
                $host = strtolower((string) $request->getHost());
                if (str_ends_with($host, '.termresult.com')) {
                    $subdomain = substr($host, 0, -strlen('.termresult.com'));
                } elseif (str_ends_with($host, '.localhost')) {
                    $subdomain = substr($host, 0, -strlen('.localhost'));
                }
            }

            if ($subdomain) {
                $cacheKey = TenantCache::publicTenantResolveKey((string) $subdomain);
                $school = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($subdomain) {
                    return School::query()
                        ->where('subdomain', $subdomain)
                        ->where('status', 'active')
                        ->first();
                });
            }
        }

        if (! $school) {
            return response()->json(['data' => null]);
        }

        $cacheKey = TenantCache::landingPagePublicKey((int) $school->id);
        $data = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($school) {
            // Always use the central DB connection. `config('database.default')` can be switched to `tenant` by IdentifyTenant.
            $central = app()->bound('central.connection') ? app('central.connection') : 'mysql';

            $content = DB::connection($central)
                ->table('landing_page_contents')
                ->where('school_id', $school->id)
                ->first();

            $theme = $school->theme ?? [];
            if (is_string($theme)) {
                $theme = json_decode($theme, true) ?? [];
            }

            if (! $content) return null;

            return [
                'hero_title' => $content->hero_title,
                'hero_subtitle' => $content->hero_subtitle,
                'hero_description' => $content->hero_description,
                'testimonials' => json_decode($content->testimonials ?? '[]', true),
                'school_email' => $content->school_email,
                'school_phone' => $content->school_phone,
                'school_address' => $content->school_address,
                'school_logo' => $theme['logo_url'] ?? (!empty($theme['logo_path']) ? url('storage/' . $theme['logo_path']) : null),
            ];
        });

        return response()->json(['data' => $data]);
    }
}

