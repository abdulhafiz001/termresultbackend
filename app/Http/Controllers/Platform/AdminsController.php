<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminsController extends Controller
{
    public function index(Request $request)
    {
        $admins = PlatformAdmin::query()
            ->orderByDesc('id')
            ->get(['id', 'full_name', 'username', 'email', 'role', 'is_active', 'last_login_at', 'created_at']);

        return response()->json(['data' => $admins]);
    }

    public function store(Request $request)
    {
        /** @var PlatformAdmin $actor */
        $actor = $request->user();

        // Only super admins can add admins
        if (($actor->role ?? null) !== 'admin') {
            return response()->json(['message' => 'Only super admins can add administrators.'], 403);
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'username' => ['required', 'string', 'max:80'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
            // Accept UI-friendly roles and map to backend roles
            'role' => ['required', 'in:admin,customer_service,support'],
        ]);

        $role = $data['role'] === 'support' ? 'customer_service' : $data['role'];

        // Basic sanitization
        $fullName = trim(strip_tags((string) $data['full_name']));
        $email = strtolower(trim((string) $data['email']));
        $username = strtolower(trim((string) $data['username']));

        if (PlatformAdmin::query()->where('email', $email)->exists()) {
            return response()->json(['message' => 'Email already exists.'], 422);
        }
        if (PlatformAdmin::query()->where('username', $username)->exists()) {
            return response()->json(['message' => 'Username already exists.'], 422);
        }

        $admin = PlatformAdmin::query()->create([
            'full_name' => $fullName,
            'username' => $username,
            'email' => $email,
            'password' => Hash::make((string) $data['password']),
            'role' => $role,
            'is_active' => true,
        ]);

        $this->logActivity($actor->id, 'platform_admin_created', [
            'created_admin_id' => $admin->id,
            'created_admin_email' => $admin->email,
            'created_admin_role' => $admin->role,
        ], $request);

        return response()->json([
            'message' => 'Admin created successfully.',
            'data' => [
                'id' => $admin->id,
                'full_name' => $admin->full_name,
                'username' => $admin->username,
                'email' => $admin->email,
                'role' => $admin->role,
                'is_active' => $admin->is_active,
                'created_at' => $admin->created_at,
            ],
        ], 201);
    }

    public function deactivate(Request $request, int $id)
    {
        /** @var PlatformAdmin $actor */
        $actor = $request->user();

        if (($actor->role ?? null) !== 'admin') {
            return response()->json(['message' => 'Only super admins can remove administrators.'], 403);
        }

        if ($actor->id === $id) {
            return response()->json(['message' => 'You cannot remove your own account.'], 422);
        }

        $admin = PlatformAdmin::query()->find($id);
        if (! $admin) return response()->json(['message' => 'Admin not found.'], 404);

        $admin->forceFill(['is_active' => false])->save();

        $this->logActivity($actor->id, 'platform_admin_deactivated', [
            'deactivated_admin_id' => $admin->id,
            'deactivated_admin_email' => $admin->email,
        ], $request);

        return response()->json(['message' => 'Admin removed successfully.']);
    }

    private function logActivity(int $platformAdminId, string $action, array $metadata, Request $request): void
    {
        DB::table('platform_admin_activities')->insert([
            'platform_admin_id' => $platformAdminId,
            'action' => $action,
            'subject_id' => null,
            'subject_type' => null,
            'metadata' => json_encode($metadata),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}


