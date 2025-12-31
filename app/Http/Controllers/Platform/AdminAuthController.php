<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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

        /** @var PlatformAdmin|null $admin */
        $admin = PlatformAdmin::query()
            ->where('email', $login)
            ->orWhere('username', $login)
            ->first();

        if (! $admin || ! Hash::check((string) $data['password'], (string) $admin->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }
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


