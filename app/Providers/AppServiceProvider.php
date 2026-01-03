<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('tenant-login', function (Request $request) {
            $ip = $request->ip() ?? 'unknown';

            // Expected roles for tenant auth in this app.
            $role = strtolower(trim((string) $request->input('role', 'unknown')));
            if (! in_array($role, ['student', 'teacher', 'school_admin'], true)) {
                $role = 'unknown';
            }

            // Use admission_number for students, username for teachers/admins.
            $rawIdentifier = $role === 'student'
                ? $request->input('admission_number')
                : $request->input('username');

            // Hash identifier to avoid storing raw usernames/admission numbers in cache keys.
            $identifier = is_string($rawIdentifier) && trim($rawIdentifier) !== ''
                ? hash('sha256', strtolower(trim($rawIdentifier)))
                : null;

            // Two-level protection:
            // - IP-only cap to reduce abuse even with random usernames/admission numbers
            // - Per-identifier cap to avoid false positives for legitimate users behind shared IPs
            $limits = [Limit::perMinute(120)->by('tenant-login:ip:' . $ip)];

            if ($identifier) {
                $limits[] = Limit::perMinute(30)->by('tenant-login:id:' . $ip . ':' . $role . ':' . $identifier);
            }

            return $limits;
        });
    }
}
