<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Support\TenantCache;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SchoolConfigController extends Controller
{
    public function show()
    {
        $school = app('tenant.school');
        $cacheKey = TenantCache::schoolConfigKey((int) $school->id);

        $payload = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($school) {
            $theme = $school->theme ?? [];

            // Ensure theme is an array (it might be a JSON string from DB)
            if (is_string($theme)) {
                $theme = json_decode($theme, true) ?? [];
            }

            // Generate full logo URL if logo_path exists (check both for backward compatibility)
            $logoUrl = null;
            if (!empty($theme['logo_path'])) {
                $logoUrl = url('storage/' . $theme['logo_path']);
            } elseif (!empty($theme['logo_url'])) {
                // Handle legacy logo_url (if it's a full URL, use it; if it's a path, prepend storage)
                $logoUrl = str_starts_with($theme['logo_url'], 'http')
                    ? $theme['logo_url']
                    : url('storage/' . $theme['logo_url']);
            }

            return [
                'id' => $school->id,
                'name' => $school->name,
                'subdomain' => $school->subdomain,
                'contact_email' => $school->contact_email,
                'contact_phone' => $school->contact_phone,
                'address' => $school->address,
                'theme' => $theme,
                'logo_url' => $logoUrl,
                'storage_quota_mb' => (int) ($school->storage_quota_mb ?? 200),
                'feature_toggles' => is_string($school->feature_toggles)
                    ? json_decode($school->feature_toggles, true) ?? []
                    : ($school->feature_toggles ?? []),
                // used by frontend for logo versioning
                'updated_at' => $school->updated_at ?? null,
            ];
        });

        return response()->json(['data' => $payload]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'theme' => ['array'],
            'theme.primary' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme.secondary' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme.panel_colors' => ['nullable', 'array'],
            'theme.panel_colors.student' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme.panel_colors.teacher' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme.panel_colors.admin' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'feature_toggles' => ['array'],
            // Feature toggles
            'feature_toggles.examinations' => ['nullable', 'boolean'],
            'feature_toggles.complaints' => ['nullable', 'boolean'],
            'feature_toggles.fee_payments' => ['nullable', 'boolean'],
            'feature_toggles.assignments' => ['nullable', 'boolean'],
            // Legacy support
            'feature_toggles.fees' => ['nullable', 'boolean'],
            'feature_toggles.materials' => ['nullable', 'boolean'],
            // Results positions policy (optional)
            'feature_toggles.results_positions' => ['nullable', 'array'],
            'feature_toggles.results_positions.global_mode' => ['nullable', 'in:all,none'],
            'feature_toggles.results_positions.top3_only_class_ids' => ['nullable', 'array'],
            'feature_toggles.results_positions.top3_only_class_ids.*' => ['integer'],
            'feature_toggles.results_positions.no_positions_class_ids' => ['nullable', 'array'],
            'feature_toggles.results_positions.no_positions_class_ids.*' => ['integer'],
        ]);

        $school = app('tenant.school');
        $central = app()->bound('central.connection') ? app('central.connection') : config('database.default');

        // Preserve existing logo_path in theme
        $existingTheme = $school->theme ?? [];
        if (is_string($existingTheme)) {
            $existingTheme = json_decode($existingTheme, true) ?? [];
        }
        $newTheme = array_key_exists('theme', $data) ? $data['theme'] : $existingTheme;
        if (!empty($existingTheme['logo_path']) && !isset($newTheme['logo_path'])) {
            $newTheme['logo_path'] = $existingTheme['logo_path'];
        }

        DB::connection($central)
            ->table('schools')
            ->where('id', $school->id)
            ->update([
                'theme' => json_encode($newTheme),
                'feature_toggles' => array_key_exists('feature_toggles', $data) ? json_encode($data['feature_toggles']) : json_encode($school->feature_toggles ?? []),
                'updated_at' => now(),
            ]);

        // Refresh bound school instance.
        $fresh = DB::connection($central)->table('schools')->where('id', $school->id)->first();
        if ($fresh) {
            $school->theme = $fresh->theme ? json_decode($fresh->theme, true) : [];
            $school->feature_toggles = $fresh->feature_toggles ? json_decode($fresh->feature_toggles, true) : [];
            app()->instance('tenant.school', $school);
        }

        TenantCache::forgetSchoolConfig($school);

        return response()->json(['message' => 'School configuration updated.']);
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ]);

        $school = app('tenant.school');
        $tenantId = TenantContext::id();
        $central = app()->bound('central.connection') ? app('central.connection') : config('database.default');

        // Delete old logo if exists
        $existingTheme = $school->theme ?? [];
        if (is_string($existingTheme)) {
            $existingTheme = json_decode($existingTheme, true) ?? [];
        }
        if (!empty($existingTheme['logo_path'])) {
            Storage::disk('public')->delete($existingTheme['logo_path']);
        }

        // Store new logo
        // Partition by tenant_id (single-db tenancy) to prevent file collisions/leakage.
        $path = $request->file('logo')->store("tenants/{$tenantId}/branding", 'public');

        // Update theme with new logo path
        $existingTheme['logo_path'] = $path;

        DB::connection($central)
            ->table('schools')
            ->where('id', $school->id)
            ->update([
                'theme' => json_encode($existingTheme),
                'updated_at' => now(),
            ]);

        // Refresh bound school instance
        $school->theme = $existingTheme;
        app()->instance('tenant.school', $school);

        TenantCache::forgetSchoolConfig($school);

        return response()->json([
            'message' => 'Logo uploaded successfully.',
            'logo_url' => url('storage/' . $path),
        ]);
    }

    public function deleteLogo()
    {
        $school = app('tenant.school');
        $central = app()->bound('central.connection') ? app('central.connection') : config('database.default');

        $existingTheme = $school->theme ?? [];
        if (is_string($existingTheme)) {
            $existingTheme = json_decode($existingTheme, true) ?? [];
        }
        
        if (!empty($existingTheme['logo_path'])) {
            Storage::disk('public')->delete($existingTheme['logo_path']);
            unset($existingTheme['logo_path']);

            DB::connection($central)
                ->table('schools')
                ->where('id', $school->id)
                ->update([
                    'theme' => json_encode($existingTheme),
                    'updated_at' => now(),
                ]);

            $school->theme = $existingTheme;
            app()->instance('tenant.school', $school);
        }

        TenantCache::forgetSchoolConfig($school);

        return response()->json(['message' => 'Logo removed successfully.']);
    }
}


