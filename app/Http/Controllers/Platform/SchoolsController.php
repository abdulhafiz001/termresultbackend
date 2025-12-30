<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Mail\SchoolApproved;
use App\Mail\SchoolDeclined;
use App\Models\School;
use App\Models\User;
use App\Services\TenantDatabaseProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

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
        if ($school->database_name && $school->status === 'active') {
            try {
                Config::set('database.connections.tenant.database', $school->database_name);
                DB::purge('tenant');
                $tenant = DB::connection('tenant');

                $students = $tenant->getSchemaBuilder()->hasTable('users')
                    ? (int) $tenant->table('users')->where('role', 'student')->count()
                    : 0;
                $teachers = $tenant->getSchemaBuilder()->hasTable('users')
                    ? (int) $tenant->table('users')->where('role', 'teacher')->count()
                    : 0;
                $admins = $tenant->getSchemaBuilder()->hasTable('users')
                    ? (int) $tenant->table('users')->where('role', 'school_admin')->count()
                    : 0;

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

    public function approve(Request $request, TenantDatabaseProvisioner $provisioner, int $id)
    {
        $school = School::query()->find($id);
        if (! $school) return response()->json(['message' => 'School not found.'], 404);
        if (! $school->contact_email) {
            return response()->json(['message' => 'School contact email is required to approve.'], 422);
        }

        // If already active and healthy, block double-approval.
        if ($school->status === 'active' && $this->tenantHealthy($school)) {
            return response()->json(['message' => 'School is already active.'], 422);
        }
        if (! in_array($school->status, ['pending', 'active'], true)) {
            return response()->json(['message' => "School is already {$school->status}."], 422);
        }

        // Provision tenant DB + run migrations (idempotent).
        try {
            $provisioner->provision($school);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to provision tenant database: '.$e->getMessage(),
            ], 500);
        }

        // Create or reset tenant admin user (in tenant DB) and always send a working password.
        Config::set('database.connections.tenant.database', $school->database_name);
        DB::purge('tenant');

        $passwordPlain = Str::random(10);
        $adminUsername = 'admin.'.$school->subdomain;

        $originalConnection = DB::getDefaultConnection();
        DB::setDefaultConnection('tenant');
        try {
            $admin = User::query()->where('username', $adminUsername)->first();
            if (! $admin) {
                User::query()->create([
                    'name' => $school->name.' Admin',
                    'username' => $adminUsername,
                    'email' => $school->contact_email,
                    'role' => 'school_admin',
                    'status' => 'active',
                    'password' => Hash::make($passwordPlain),
                ]);
            } else {
                $admin->forceFill([
                    'email' => $school->contact_email,
                    'status' => 'active',
                    'password' => Hash::make($passwordPlain),
                ])->save();
            }
        } finally {
            DB::setDefaultConnection($originalConnection);
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

        return response()->json(['message' => 'School approved and provisioned.']);
    }

    public function purge(Request $request, int $id)
    {
        $data = $request->validate([
            'confirm' => ['required', 'string', 'in:DELETE'],
        ]);

        $school = School::query()->find($id);
        if (! $school) return response()->json(['message' => 'School not found.'], 404);

        $dbName = (string) ($school->database_name ?? '');
        $subdomain = (string) ($school->subdomain ?? '');

        // Best-effort: delete tenant files referenced in tenant DB before dropping it.
        if ($dbName) {
            try {
                Config::set('database.connections.tenant.database', $dbName);
                DB::purge('tenant');
                $tenant = DB::connection('tenant');
                $schema = $tenant->getSchemaBuilder();

                $paths = collect();

                if ($schema->hasTable('exam_question_submissions')) {
                    $rows = $tenant->table('exam_question_submissions')->get(['paper_pdf_path', 'source_file_path']);
                    foreach ($rows as $r) {
                        if (!empty($r->paper_pdf_path)) $paths->push((string) $r->paper_pdf_path);
                        if (!empty($r->source_file_path)) $paths->push((string) $r->source_file_path);
                    }
                }

                if ($schema->hasTable('assignments')) {
                    $rows = $tenant->table('assignments')->whereNotNull('image_path')->pluck('image_path');
                    foreach ($rows as $p) $paths->push((string) $p);
                }

                if ($schema->hasTable('study_materials')) {
                    $rows = $tenant->table('study_materials')->whereNotNull('file_path')->pluck('file_path');
                    foreach ($rows as $p) $paths->push((string) $p);
                }

                $paths = $paths->filter()->unique()->values();
                foreach ($paths as $p) {
                    try { Storage::disk('public')->delete($p); } catch (\Throwable $e) {}
                }
            } catch (\Throwable $e) {
                // ignore tenant file cleanup failures
            }
        }

        // Delete directories keyed by subdomain (logos + exams).
        if ($subdomain) {
            try { Storage::disk('public')->deleteDirectory('school-logos/'.$subdomain); } catch (\Throwable $e) {}
            try { Storage::disk('public')->deleteDirectory('exams/'.$subdomain); } catch (\Throwable $e) {}
        }

        // Drop tenant DB and delete central record.
        try {
            if ($dbName) {
                $adminConnection = env('TENANT_ADMIN_CONNECTION', config('database.default'));
                DB::connection($adminConnection)->statement("DROP DATABASE IF EXISTS `{$dbName}`");
            }
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to drop tenant database: '.$e->getMessage()], 500);
        }

        // Delete school (cascades tokens/landing content; traffic nulls).
        $school->delete();

        return response()->json(['message' => 'School deleted permanently.']);
    }

    private function tenantHealthy(School $school): bool
    {
        if (! $school->database_name) return false;
        try {
            Config::set('database.connections.tenant.database', $school->database_name);
            DB::purge('tenant');
            $tenant = DB::connection('tenant');
            $schema = $tenant->getSchemaBuilder();
            return $schema->hasTable('users') && $schema->hasTable('announcements');
        } catch (\Throwable $e) {
            return false;
        }
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
        if ($school->status !== 'active' || ! $school->database_name) {
            return response()->json(['message' => 'School must be active with a provisioned database.'], 422);
        }

        Config::set('database.connections.tenant.database', $school->database_name);
        DB::purge('tenant');

        $originalConnection = DB::getDefaultConnection();
        DB::setDefaultConnection('tenant');

        try {
            $admin = User::query()->where('role', 'school_admin')->orderBy('id')->first();
            if (! $admin) {
                return response()->json(['message' => 'No admin user found in tenant database.'], 404);
            }

            $newPassword = Str::random(10);
            $admin->forceFill(['password' => Hash::make($newPassword)])->save();

            DB::setDefaultConnection($originalConnection);

            return response()->json([
                'message' => 'Admin password reset successfully.',
                'data' => [
                    'username' => $admin->username,
                    'new_password' => $newPassword,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::setDefaultConnection($originalConnection);
            return response()->json(['message' => 'Failed to reset password: '.$e->getMessage()], 500);
        }
    }

    private function tenantPortalBaseUrl(string $subdomain): string
    {
        $template = env('TENANT_PORTAL_URL_TEMPLATE', 'https://{subdomain}.termresult.com');
        return rtrim(str_replace('{subdomain}', $subdomain, $template), '/');
    }
}


