<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdministratorsController extends Controller
{
    public function index()
    {
        $admins = DB::table('users')
            ->where('role', 'school_admin')
            ->orderByDesc('id')
            ->get()
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'username' => $u->username,
                    'email' => $u->email,
                    'status' => $u->status,
                    'created_at' => $u->created_at,
                ];
            });

        return response()->json([
            'data' => $admins,
            'stats' => [
                'total' => $admins->count(),
                'active' => $admins->where('status', 'active')->count(),
                'disabled' => $admins->where('status', 'disabled')->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $id = DB::table('users')->insertGetId([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'role' => 'school_admin',
            'status' => 'active',
            'password' => Hash::make($data['password']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', "unique:users,username,{$id}"],
            'email' => ['nullable', 'email', 'max:255', "unique:users,email,{$id}"],
            'status' => ['required', 'in:active,disabled,restricted'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        $admin = DB::table('users')->where('id', $id)->where('role', 'school_admin')->first();
        if (! $admin) {
            return response()->json(['message' => 'Administrator not found.'], 404);
        }

        $update = [
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'status' => $data['status'],
            'updated_at' => now(),
        ];
        if (! empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }

        DB::table('users')->where('id', $id)->update($update);

        return response()->json(['message' => 'Administrator updated.']);
    }

    public function destroy(int $id)
    {
        $admin = DB::table('users')->where('id', $id)->where('role', 'school_admin')->first();
        if (! $admin) {
            return response()->json(['message' => 'Administrator not found.'], 404);
        }

        // Prevent deleting yourself
        if ($id === auth()->id()) {
            return response()->json(['message' => 'You cannot delete your own account.'], 403);
        }

        DB::table('users')->where('id', $id)->delete();

        return response()->json(['message' => 'Administrator deleted.']);
    }
}


