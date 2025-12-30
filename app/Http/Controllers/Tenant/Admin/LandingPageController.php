<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Support\TenantCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LandingPageController extends Controller
{
    public function show()
    {
        $school = app('tenant.school');
        $cacheKey = TenantCache::landingPageAdminKey((int) $school->id);
        $data = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($school) {
            $central = app()->bound('central.connection') ? app('central.connection') : config('database.default');

            $row = DB::connection($central)
                ->table('landing_page_contents')
                ->where('school_id', $school->id)
                ->first();

            return $row ? [
                'hero_title' => $row->hero_title,
                'hero_subtitle' => $row->hero_subtitle,
                'hero_description' => $row->hero_description,
                'testimonials' => json_decode($row->testimonials ?? '[]', true),
                'school_email' => $row->school_email,
                'school_phone' => $row->school_phone,
                'school_address' => $row->school_address,
            ] : [
                'hero_title' => null,
                'hero_subtitle' => null,
                'hero_description' => null,
                'testimonials' => [],
                'school_email' => $school->contact_email,
                'school_phone' => $school->contact_phone,
                'school_address' => $school->address,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'hero_title' => ['nullable', 'string', 'max:255'],
            'hero_subtitle' => ['nullable', 'string', 'max:255'],
            'hero_description' => ['nullable', 'string', 'max:2000'],
            'school_email' => ['nullable', 'email', 'max:255'],
            'school_phone' => ['nullable', 'string', 'max:255'],
            'school_address' => ['nullable', 'string', 'max:255'],
            'testimonials' => ['nullable', 'array'],
            'testimonials.*.text' => ['required_with:testimonials', 'string', 'max:500'],
            'testimonials.*.author' => ['nullable', 'string', 'max:100'],
            'testimonials.*.role' => ['nullable', 'string', 'max:100'],
        ]);

        $school = app('tenant.school');
        $central = app()->bound('central.connection') ? app('central.connection') : config('database.default');

        DB::connection($central)
            ->table('landing_page_contents')
            ->updateOrInsert(
                ['school_id' => $school->id],
                [
                    'hero_title' => $data['hero_title'] ?? null,
                    'hero_subtitle' => $data['hero_subtitle'] ?? null,
                    'hero_description' => $data['hero_description'] ?? null,
                    'testimonials' => array_key_exists('testimonials', $data) ? json_encode($data['testimonials'] ?? []) : json_encode([]),
                    'school_email' => $data['school_email'] ?? null,
                    'school_phone' => $data['school_phone'] ?? null,
                    'school_address' => $data['school_address'] ?? null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

        TenantCache::forgetLandingPage($school);

        return response()->json(['message' => 'Landing page content updated.']);
    }
}


