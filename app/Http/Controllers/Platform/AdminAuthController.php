<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AdminAuthController extends Controller
{
    public function exists()
    {
        return response()->json([
            'data' => [
                'has_admin' => PlatformAdmin::query()->exists(),
            ],
        ]);
    }

    public function setup(Request $request)
    {
        if (PlatformAdmin::query()->exists()) {
            return response()->json(['message' => 'Setup already completed.'], 422);
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:80', 'unique:platform_admins,username'],
            'email' => ['required', 'email', 'max:255', 'unique:platform_admins,email'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
            'role' => ['nullable', 'in:admin,customer_service'],
        ]);

        try {
            $admin = PlatformAdmin::query()->create([
                'full_name' => trim($data['full_name']),
                'username' => strtolower(trim($data['username'])),
                'email' => strtolower(trim($data['email'])),
                'password' => Hash::make($data['password']),
                'role' => $data['role'] ?? 'admin',
                'is_active' => true,
            ]);

            $token = $admin->createToken('platform-admin')->plainTextToken;
        } catch (\Throwable $e) {
            \Log::error('Platform admin setup failed.', [
                'error' => $e->getMessage(),
            ]);
            $msg = config('app.debug')
                ? $e->getMessage()
                : 'Failed to create the first admin.';
            return response()->json(['message' => $msg], 500);
        }

        return response()->json([
            'data' => [
                'token' => $token,
                'admin' => [
                    'id' => $admin->id,
                    'full_name' => $admin->full_name,
                    'username' => $admin->username,
                    'email' => $admin->email,
                    'role' => $admin->role,
                ],
            ],
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'], // username or email
            'password' => ['required', 'string', 'max:255'],
        ]);

        $login = strtolower(trim((string) $data['login']));
        $ip = $request->ip() ?? 'unknown';

        // Rate limiting: 5 attempts per 5 minutes, then 5 minute block
        $rateLimitKey = 'platform-admin-login:' . $ip . ':' . $login;
        $maxAttempts = 5;
        $decayMinutes = 5;

        // Check if rate limit has expired (if availableIn returns 0 or negative, it's expired)
        $availableIn = RateLimiter::availableIn($rateLimitKey);
        if ($availableIn <= 0 && RateLimiter::attempts($rateLimitKey) > 0) {
            // Rate limit has expired, clear it
            RateLimiter::clear($rateLimitKey);
        }

        // Check rate limiting only if there are actual attempts
        if (RateLimiter::attempts($rateLimitKey) >= $maxAttempts) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            if ($seconds > 0) {
                $minutes = ceil($seconds / 60);
                
                return response()->json([
                    'message' => 'Too many login attempts. Please try again in ' . $minutes . ' minute(s).',
                    'blocked_until' => now()->addSeconds($seconds)->toIso8601String(),
                    'seconds_remaining' => $seconds,
                ], 429);
            }
        }

        /** @var PlatformAdmin|null $admin */
        $admin = PlatformAdmin::query()
            ->where('email', $login)
            ->orWhere('username', $login)
            ->first();

        if (! $admin || ! Hash::check((string) $data['password'], (string) $admin->password)) {
            // Increment rate limiter on failed attempt
            RateLimiter::hit($rateLimitKey, $decayMinutes * 60);
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        // Clear rate limiter on successful login
        RateLimiter::clear($rateLimitKey);
        if (! ($admin->is_active ?? false)) {
            return response()->json(['message' => 'Account disabled.'], 403);
        }

        $admin->forceFill(['last_login_at' => now()])->save();

        $tokenName = 'platform-admin:' . Str::random(6);
        $token = $admin->createToken($tokenName)->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'admin' => [
                    'id' => $admin->id,
                    'full_name' => $admin->full_name,
                    'username' => $admin->username,
                    'email' => $admin->email,
                    'role' => $admin->role,
                ],
            ],
        ]);
    }

    public function me(Request $request)
    {
        /** @var PlatformAdmin $admin */
        $admin = $request->user();

        return response()->json([
            'data' => [
                'id' => $admin->id,
                'full_name' => $admin->full_name,
                'username' => $admin->username,
                'email' => $admin->email,
                'role' => $admin->role,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['ok' => true]);
    }
}


