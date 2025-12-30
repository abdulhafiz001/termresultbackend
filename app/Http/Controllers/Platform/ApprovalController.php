<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Mail\SchoolApproved;
use App\Mail\SchoolDeclined;
use App\Models\School;
use App\Models\SchoolApprovalToken;
use App\Models\User;
use App\Services\TenantDatabaseProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ApprovalController extends Controller
{
    public function accept(Request $request, TenantDatabaseProvisioner $provisioner, string $token)
    {
        $approval = $this->findValidApproval($token);
        if (! $approval) {
            return response()->view('approvals/result', ['title' => 'Invalid Link', 'message' => 'This approval link is invalid or expired.'], 400);
        }

        $school = $approval->school;

        if ($school->status !== 'pending') {
            return response()->view('approvals/result', ['title' => 'Already Processed', 'message' => "This school is already {$school->status}."], 200);
        }

        // Provision tenant DB + run tenant migrations (can throw; don't mark token used until success).
        try {
            $provisioner->provision($school);
        } catch (\Throwable $e) {
            return response()->view('approvals/result', [
                'title' => 'Provisioning Failed',
                'message' => 'School approval failed while provisioning the tenant database. Please try again or contact support.',
            ], 500);
        }

        // Create tenant admin user (in tenant DB).
        // Ensure the tenant connection is bound to the correct tenant database before switching connections.
        // This mirrors the pattern in IdentifyTenant middleware.
        Config::set('database.connections.tenant.database', $school->database_name);
        DB::purge('tenant');
        
        // Store original connection to restore it in finally block
        $originalConnection = DB::getDefaultConnection();
        DB::setDefaultConnection('tenant');
        
        try {
            $passwordPlain = Str::random(10);
            $adminUsername = 'admin.'.$school->subdomain;

            $adminUser = User::query()->where('username', $adminUsername)->first();
            if (! $adminUser) {
                $adminUser = User::query()->create([
                    'name' => $school->name.' Admin',
                    'username' => $adminUsername,
                    'email' => $school->contact_email,
                    'role' => 'school_admin',
                    'status' => 'active',
                    'password' => Hash::make($passwordPlain),
                ]);
            } else {
                $adminUser->forceFill([
                    'email' => $school->contact_email,
                    'status' => 'active',
                    'password' => Hash::make($passwordPlain),
                ])->save();
            }
        } finally {
            // Always restore the original database connection, even if an exception occurs
            DB::setDefaultConnection($originalConnection);
        }

        DB::transaction(function () use ($school, $approval) {
            $approval->forceFill(['used_at' => now()])->save();
            $school->forceFill(['status' => 'active', 'decline_reason' => null])->save();
        });

        // Always write audit logs to the central DB (not tenant DB).
        $central = app()->bound('central.connection') ? app('central.connection') : config('database.default');
        DB::connection($central)->table('audit_logs')->insert([
            'action' => 'school_approved',
            'subject_type' => School::class,
            'subject_id' => $school->id,
            'metadata' => json_encode(['admin_username' => $adminUsername]),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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

        // Log before queuing
        \Log::info('Queuing SchoolApproved email', [
            'school_id' => $school->id,
            'school_name' => $school->name,
            'email' => $school->contact_email,
        ]);

        // Send synchronously for approvals to avoid long delays when queue workers aren't running.
        Mail::to($school->contact_email)->send($mailable);

        return response()->view('approvals/result', [
            'title' => 'School Approved',
            'message' => 'School has been approved and provisioned successfully. A welcome email was sent to the school.',
        ]);
    }

    public function declineForm(Request $request, string $token)
    {
        $approval = $this->findValidApproval($token);
        if (! $approval) {
            return response()->view('approvals/result', ['title' => 'Invalid Link', 'message' => 'This decline link is invalid or expired.'], 400);
        }

        return response()->view('approvals/decline', [
            'token' => $token,
            'school' => $approval->school,
        ]);
    }

    public function decline(Request $request, string $token)
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $approval = $this->findValidApproval($token);
        if (! $approval) {
            return response()->view('approvals/result', ['title' => 'Invalid Link', 'message' => 'This decline link is invalid or expired.'], 400);
        }

        $school = $approval->school;

        if ($school->status !== 'pending') {
            return response()->view('approvals/result', ['title' => 'Already Processed', 'message' => "This school is already {$school->status}."], 200);
        }

        DB::transaction(function () use ($approval, $school, $request) {
            $approval->forceFill(['used_at' => now()])->save();
            $school->forceFill([
                'status' => 'declined',
                'decline_reason' => (string) $request->input('reason'),
            ])->save();
        });

        $central = app()->bound('central.connection') ? app('central.connection') : config('database.default');
        DB::connection($central)->table('audit_logs')->insert([
            'action' => 'school_declined',
            'subject_type' => School::class,
            'subject_id' => $school->id,
            'metadata' => json_encode(['reason' => (string) $request->input('reason')]),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Send synchronously on shared hosting (no queue worker needed).
        Mail::to($school->contact_email)->send(new SchoolDeclined($school, (string) $request->input('reason')));

        return response()->view('approvals/result', [
            'title' => 'School Declined',
            'message' => 'School registration has been declined and the reason was sent to the school.',
        ]);
    }

    private function findValidApproval(string $plainToken): ?SchoolApprovalToken
    {
        $hash = hash('sha256', $plainToken);

        $approval = SchoolApprovalToken::query()
            ->with('school')
            ->where('token_hash', $hash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        return $approval;
    }

    private function tenantPortalBaseUrl(string $subdomain): string
    {
        // Local dev example:
        // TENANT_PORTAL_URL_TEMPLATE="http://{subdomain}.localhost:5173"
        // Production example:
        // TENANT_PORTAL_URL_TEMPLATE="https://{subdomain}.termresult.com"
        $template = env('TENANT_PORTAL_URL_TEMPLATE', 'https://{subdomain}.termresult.com');
        $url = str_replace('{subdomain}', $subdomain, $template);
        return rtrim($url, '/');
    }
}


