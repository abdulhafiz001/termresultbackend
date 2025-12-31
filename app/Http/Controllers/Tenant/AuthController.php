<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\TenantLoginRequest;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(TenantLoginRequest $request)
    {
        $school = app()->bound('tenant.school') ? app('tenant.school') : null;
        if (! $school) {
            return response()->json(['message' => 'Tenant not resolved.'], 400);
        }

        // Enforce school-level restrictions (set by TermResult platform admin).
        $schoolRestrictions = $school->restrictions ?? [];
        if (is_string($schoolRestrictions)) {
            $schoolRestrictions = json_decode($schoolRestrictions, true) ?? [];
        }

        if ((bool) ($schoolRestrictions['login_restricted'] ?? false)) {
            return response()->json([
                'message' => 'Login has been restricted for this school.',
                'reason' => (string) ($schoolRestrictions['login_reason'] ?? 'Please contact TermResult support.'),
            ], 403);
        }

        $role = $request->input('role');

        $query = User::query()->where('role', $role);

        if ($role === 'student') {
            $admissionNumber = $request->input('admission_number');
            if (! $admissionNumber) {
                return response()->json(['message' => 'admission_number is required for student login.'], 422);
            }
            $query->where('admission_number', $admissionNumber);
        } else {
            $username = $request->input('username');
            if (! $username) {
                return response()->json(['message' => 'username is required for this login.'], 422);
            }
            $query->where('username', $username);
        }

        $user = $query->first();

        if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if ($user->status === 'disabled') {
            return response()->json([
                'message' => 'Account is disabled.',
                'reason' => $user->restriction_reason,
            ], 403);
        }

        // Allow "restricted" users to login unless "login" restriction is set.
        $restrictions = $user->restrictions ?? [];
        if (is_string($restrictions)) {
            $restrictions = json_decode($restrictions, true) ?? [];
        }

        if ($user->status === 'restricted' && in_array('login', (array) $restrictions, true)) {
            return response()->json([
                'message' => 'Login is restricted for this account.',
                'reason' => $user->restriction_reason,
            ], 403);
        }

        $token = $user->createToken('tenant', [$role])->plainTextToken;

        // Teacher activity log (tenant DB)
        if ($role === 'teacher') {
            // Guard against tenants that haven't run newer migrations yet.
            if (Schema::hasTable('teacher_activities')) {
                $tenantId = TenantContext::id();
                DB::table('teacher_activities')->insert([
                    'tenant_id' => $tenantId,
                    'teacher_id' => $user->id,
                    'action' => 'teacher_login',
                    'metadata' => json_encode(['role' => $role]),
                    'ip' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 5000),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'admission_number' => $user->admission_number,
                'role' => $user->role,
            ],
            'tenant' => [
                'id' => $school->id,
                'name' => $school->name,
                'subdomain' => $school->subdomain,
                'theme' => $school->theme ?? [],
                'feature_toggles' => $school->feature_toggles ?? [],
            ],
        ]);
    }

    public function verifyAdmissionNumber(Request $request)
    {
        $school = app()->bound('tenant.school') ? app('tenant.school') : null;
        if (! $school) {
            return response()->json(['message' => 'Tenant not resolved.'], 400);
        }

        $data = $request->validate([
            'admission_number' => ['required', 'string', 'max:255'],
        ]);

        $user = User::query()
            ->where('role', 'student')
            ->where('admission_number', $data['admission_number'])
            ->first();

        if (! $user) {
            return response()->json([
                'exists' => false,
                'message' => 'Admission number not found.',
            ], 404);
        }

        if ($user->status === 'disabled') {
            return response()->json([
                'exists' => false,
                'message' => 'This account is disabled. Please contact your administrator.',
            ], 403);
        }

        return response()->json([
            'exists' => true,
            'message' => 'Admission number verified.',
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $school = app()->bound('tenant.school') ? app('tenant.school') : null;
        if (! $school) {
            return response()->json(['message' => 'Tenant not resolved.'], 400);
        }

        $data = $request->validate([
            'admission_number' => ['required', 'string', 'max:255'],
            'new_password' => ['required', 'string', 'min:4', 'max:255'],
            'confirm_password' => ['required', 'string', 'same:new_password'],
        ]);

        $user = User::query()
            ->where('role', 'student')
            ->where('admission_number', $data['admission_number'])
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'admission_number' => ['Admission number not found.'],
            ]);
        }

        if ($user->status === 'disabled') {
            throw ValidationException::withMessages([
                'admission_number' => ['This account is disabled. Please contact your administrator.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($data['new_password']),
        ]);

        return response()->json(['message' => 'Password changed successfully. You can now login with your new password.']);
    }
}
