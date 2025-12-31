<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Mail\SchoolApproved;
use App\Mail\SchoolDeclined;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Stancl\Tenancy\Database\Models\Tenant;

class SchoolsController extends Controller
{
    public function pending()
    {
        return response()->json([
            'data' => School::query()
                ->where('status', 'pending')
                ->orderByDesc('id')
                ->limit(200)
                ->get(),
        ]);
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => ['nullable', 'in:pending,active,declined'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $q = isset($data['q']) ? trim(strtolower((string) $data['q'])) : null;

        $schools = School::query()
            ->when(! empty($data['status']), fn ($qq) => $qq->where('status', $data['status']))
            ->when($q, function ($qq) use ($q) {
                $like = '%'.$q.'%';
                $qq->whereRaw('lower(name) like ?', [$like])
                    ->orWhereRaw('lower(subdomain) like ?', [$like])
                    ->orWhereRaw('lower(contact_email) like ?', [$like]);
            })
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        return response()->json(['data' => $schools]);
    }

    public function show(int $id)
    {
        $school = School::query()->find($id);
        if (! $school) return response()->json(['message' => 'School not found.'], 404);

        // Best-effort tenant stats.
        $stats = null;
        if ($school->status === 'active') {
            try {
                $tenantId = (string) $school->id;
                $students = (int) DB::table('users')->where('tenant_id', $tenantId)->where('role', 'student')->count();
                $teachers = (int) DB::table('users')->where('tenant_id', $tenantId)->where('role', 'teacher')->count();
                $admins = (int) DB::table('users')->where('tenant_id', $tenantId)->where('role', 'school_admin')->count();

                $stats = [
                    'students' => $students,
                    'teachers' => $teachers,
                    'admins' => $admins,
                ];
            } catch (\Throwable $e) {
                $stats = null;
            }
        }

        return response()->json(['data' => ['school' => $school, 'stats' => $stats]]);
    }

    public function updateStorageQuota(Request $request, int $id)
    {
        $school = School::query()->find($id);
        if (! $school) return response()->json(['message' => 'School not found.'], 404);

        $data = $request->validate([
            'storage_quota_mb' => ['required', 'integer', 'min:50', 'max:10240'], // 50MB - 10GB
        ]);

        $school->forceFill([
            'storage_quota_mb' => (int) $data['storage_quota_mb'],
        ])->save();

        return response()->json(['message' => 'Storage quota updated.', 'data' => ['storage_quota_mb' => (int) $school->storage_quota_mb]]);
    }

    public function approve(Request $request, int $id)
    {
        $school = School::query()->find($id);
        if (! $school) return response()->json(['message' => 'School not found.'], 404);
        if (! $school->contact_email) {
            return response()->json(['message' => 'School contact email is required to approve.'], 422);
        }

        // If already active, allow re-sending admin credentials but don't flip status again.
        if ($school->status === 'active') {
            return response()->json(['message' => 'School is already active.'], 422);
        }
        if (! in_array($school->status, ['pending', 'active'], true)) {
            return response()->json(['message' => "School is already {$school->status}."], 422);
        }

        // Single-database tenancy: ensure a tenant record exists for cache/redis isolation.
        $tenantId = (string) $school->id;
        Tenant::query()->firstOrCreate(
            ['id' => $tenantId],
            [
                'data' => [
                    'school_id' => $school->id,
                    'subdomain' => $school->subdomain,
                ],
            ]
        );

        // Create or reset tenant admin user (in central users table scoped by tenant_id).
        $passwordPlain = Str::random(10);
        $adminUsername = 'admin.'.$school->subdomain;

        tenancy()->initialize($tenantId);
        try {
            $admin = User::query()->where('username', $adminUsername)->first();
            if (! $admin) {
                User::query()->create([
                    'tenant_id' => $tenantId,
                    'name' => $school->name.' Admin',
                    'username' => $adminUsername,
                    'email' => $school->contact_email,
                    'role' => 'school_admin',
                    'status' => 'active',
                    'password' => Hash::make($passwordPlain),
                ]);
            } else {
                $admin->forceFill([
                    'tenant_id' => $tenantId,
                    'email' => $school->contact_email,
                    'status' => 'active',
                    'password' => Hash::make($passwordPlain),
                ])->save();
            }
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }

        // Only mark active after provisioning + admin creation succeeds.
        if ($school->status !== 'active') {
            $school->forceFill([
                'status' => 'active',
                'decline_reason' => null,
            ])->save();
        }

        $schoolBase = $this->tenantPortalBaseUrl($school->subdomain);
        $mailable = new SchoolApproved(
            $school,
            $adminUsername,
            $passwordPlain,
            [
                'landing' => $schoolBase.'/',
                'admin' => $schoolBase.'/school/login',
                'teacher' => $schoolBase.'/teacher/login',
                'student' => $schoolBase.'/student/login',
            ]
        );

        // Send synchronously for platform approvals to reduce delivery delays.
        Mail::to($school->contact_email)->send($mailable);

        return response()->json(['message' => 'School approved.']);
    }

    public function purge(Request $request, int $id)
    {
        $data = $request->validate([
            'confirm' => ['required', 'string', 'in:DELETE'],
        ]);

        $school = School::query()->find($id);
        if (! $school) return response()->json(['message' => 'School not found.'], 404);

        $tenantId = (string) $school->id;
        $subdomain = (string) ($school->subdomain ?? '');

        // Delete directories keyed by subdomain (logos + exams).
        if ($subdomain) {
            try { Storage::disk('public')->deleteDirectory('school-logos/'.$subdomain); } catch (\Throwable $e) {}
            try { Storage::disk('public')->deleteDirectory('exams/'.$subdomain); } catch (\Throwable $e) {}
        }

        // Also delete tenant-partitioned directories (new single-db storage layout, best-effort).
        try { Storage::disk('public')->deleteDirectory('tenants/'.$tenantId); } catch (\Throwable $e) {}

        // Delete tenant + school. Tenant-owned data is cascaded via FK constraints on tenant_id.
        try {
            Tenant::query()->where('id', $tenantId)->delete();
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to delete tenant data: '.$e->getMessage()], 500);
        }

        $school->delete(); // central record

        return response()->json(['message' => 'School deleted permanently.']);
    }

    public function decline(Request $request, int $id)
    {
        $school = School::query()->find($id);
        if (! $school) return response()->json(['message' => 'School not found.'], 404);
        if ($school->status !== 'pending') {
            return response()->json(['message' => "School is already {$school->status}."], 422);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $school->forceFill([
            'status' => 'declined',
            'decline_reason' => (string) $data['reason'],
        ])->save();

        if ($school->contact_email) {
            // Send synchronously on shared hosting (no queue worker needed).
            Mail::to($school->contact_email)->send(new SchoolDeclined($school, (string) $data['reason']));
        }

        return response()->json(['message' => 'School declined.']);
    }

    public function restrictLogin(Request $request, int $id)
    {
        $school = School::query()->find($id);
        if (! $school) return response()->json(['message' => 'School not found.'], 404);

        $data = $request->validate([
            'restrict' => ['required', 'boolean'],
            'reason' => ['required_if:restrict,true', 'nullable', 'string', 'max:500'],
        ]);

        $restrictions = $school->restrictions ?? [];
        if (is_string($restrictions)) {
            $restrictions = json_decode($restrictions, true) ?? [];
        }
        $restrictions['login_restricted'] = (bool) $data['restrict'];
        $restrictions['login_reason'] = $data['restrict'] ? (string) $data['reason'] : null;

        $school->forceFill(['restrictions' => $restrictions])->save();

        return response()->json(['message' => $data['restrict'] ? 'Login restricted.' : 'Login restriction removed.']);
    }

    public function restrictSite(Request $request, int $id)
    {
        $school = School::query()->find($id);
        if (! $school) return response()->json(['message' => 'School not found.'], 404);

        $data = $request->validate([
            'restrict' => ['required', 'boolean'],
            'reason' => ['required_if:restrict,true', 'nullable', 'string', 'max:500'],
        ]);

        $restrictions = $school->restrictions ?? [];
        if (is_string($restrictions)) {
            $restrictions = json_decode($restrictions, true) ?? [];
        }
        $restrictions['site_restricted'] = (bool) $data['restrict'];
        $restrictions['site_reason'] = $data['restrict'] ? (string) $data['reason'] : null;

        $school->forceFill(['restrictions' => $restrictions])->save();

        return response()->json(['message' => $data['restrict'] ? 'Site access restricted.' : 'Site restriction removed.']);
    }

    public function resetAdminPassword(Request $request, int $id)
    {
        $school = School::query()->find($id);
        if (! $school) return response()->json(['message' => 'School not found.'], 404);
        if ($school->status !== 'active') {
            return response()->json(['message' => 'School must be active.'], 422);
        }

        $tenantId = (string) $school->id;
        tenancy()->initialize($tenantId);
        try {
            $admin = User::query()->where('role', 'school_admin')->orderBy('id')->first();
            if (! $admin) {
                return response()->json(['message' => 'No admin user found in tenant database.'], 404);
            }

            $newPassword = Str::random(10);
            $admin->forceFill(['password' => Hash::make($newPassword)])->save();

            return response()->json([
                'message' => 'Admin password reset successfully.',
                'data' => [
                    'username' => $admin->username,
                    'new_password' => $newPassword,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to reset password: '.$e->getMessage()], 500);
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }

    private function tenantPortalBaseUrl(string $subdomain): string
    {
        $template = env('TENANT_PORTAL_URL_TEMPLATE', 'https://{subdomain}.termresult.com');
        return rtrim(str_replace('{subdomain}', $subdomain, $template), '/');
    }
}


