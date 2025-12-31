<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Mail\SchoolApproved;
use App\Mail\SchoolDeclined;
use App\Models\School;
use App\Models\SchoolApprovalToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Tenant;

class ApprovalController extends Controller
{
    public function accept(Request $request, string $token)
    {
        $approval = $this->findValidApproval($token);
        if (! $approval) {
            return response()->view('approvals/result', ['title' => 'Invalid Link', 'message' => 'This approval link is invalid or expired.'], 400);
        }

        $school = $approval->school;

        if ($school->status !== 'pending') {
            return response()->view('approvals/result', ['title' => 'Already Processed', 'message' => "This school is already {$school->status}."], 200);
        }

        // Single-database tenancy: no DB provisioning. Ensure a tenant record exists for cache/redis isolation.
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

        // Create/reset tenant admin user (in central users table, scoped by tenant_id).
        tenancy()->initialize($tenantId);
        try {
            $passwordPlain = Str::random(10);
            $adminUsername = 'admin.'.$school->subdomain;

            $adminUser = User::query()->where('username', $adminUsername)->first();
            if (! $adminUser) {
                $adminUser = User::query()->create([
                    'tenant_id' => $tenantId,
                    'name' => $school->name.' Admin',
                    'username' => $adminUsername,
                    'email' => $school->contact_email,
                    'role' => 'school_admin',
                    'status' => 'active',
                    'password' => Hash::make($passwordPlain),
                ]);
            } else {
                $adminUser->forceFill([
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

        Mail::to($school->contact_email)->queue($mailable);

        return response()->view('approvals/result', [
            'title' => 'School Approved',
            'message' => 'School has been approved successfully. A welcome email was sent to the school.',
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

        Mail::to($school->contact_email)->queue(new SchoolDeclined($school, (string) $request->input('reason')));

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


