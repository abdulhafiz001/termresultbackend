<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\SchoolPaymentReceived;
use App\Models\School;
use App\Services\PaystackService;
use App\Support\TenantCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Stancl\Tenancy\Database\Models\Tenant;

class PaystackWebhookController extends Controller
{
    private function notifySchoolIfNeeded(School $school, string $reference): void
    {
        if (empty($school->contact_email)) return;
        if (! \Schema::hasColumn('payments', 'school_notified_at')) return;

        $tenantId = (string) $school->id;
        $payment = DB::table('payments')->where('tenant_id', $tenantId)->where('reference', $reference)->first();
        if (! $payment || ($payment->status ?? null) !== 'success') return;
        if (! empty($payment->school_notified_at)) return;

        try {
            $student = DB::table('users')->where('tenant_id', $tenantId)->where('id', (int) $payment->student_id)->first();
            $profile = DB::table('student_profiles')->where('tenant_id', $tenantId)->where('user_id', (int) $payment->student_id)->first();
            $class = $payment->class_id
                ? DB::table('classes')->where('tenant_id', $tenantId)->where('id', (int) $payment->class_id)->first()
                : null;

            Mail::to((string) $school->contact_email)->queue(new SchoolPaymentReceived($school, $payment, $student, $profile, $class));

            DB::table('payments')->where('tenant_id', $tenantId)->where('id', (int) $payment->id)->update([
                'school_notified_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Failed to send school payment notification email (webhook).', [
                'reference' => $reference,
                'school_id' => $school->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handle(Request $request, PaystackService $paystack)
    {
        $payload = $request->all();

        $event = $payload['event'] ?? null;
        $data = $payload['data'] ?? null;
        $metadata = $data['metadata'] ?? [];

        $subdomain = $metadata['school_subdomain'] ?? null;
        $reference = $data['reference'] ?? null;

        if (! $subdomain || ! $reference) {
            return response()->json(['message' => 'Invalid payload.'], 400);
        }

        // Validate webhook signature first.
        $signature = $request->header('x-paystack-signature');
        $secret = env('PAYSTACK_SECRET_KEY');
        if (! $secret) {
            return response()->json(['message' => 'PAYSTACK_SECRET_KEY not configured.'], 500);
        }
        $computed = hash_hmac('sha512', $request->getContent(), $secret);
        if (! $signature || ! hash_equals($computed, $signature)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $school = School::query()->where('subdomain', $subdomain)->where('status', 'active')->first();
        if (! $school) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        // Initialize tenancy context (single-db).
        $tenantId = (string) $school->id;
        if (! Tenant::query()->where('id', $tenantId)->exists()) {
            Tenant::create([
                'id' => $tenantId,
                'data' => [
                    'school_id' => $school->id,
                    'subdomain' => $school->subdomain,
                ],
            ]);
        }
        tenancy()->initialize($tenantId);
        app()->instance('tenant.school', $school);

        try {
            $settings = DB::table('payment_settings')->where('tenant_id', $tenantId)->orderByDesc('id')->first();
            if (! $settings || ($settings->mode ?? 'manual') !== 'automatic') {
                return response()->json(['message' => 'Paystack not configured for tenant.'], 422);
            }

            // Verify with Paystack before recording success.
            $verified = $paystack->verify($reference);
            $status = $verified['data']['status'] ?? null;
            $transactionId = $verified['data']['id'] ?? null;

            if ($status === 'success') {
                // Only record successful payments (no pending/failed records).
                $exists = DB::table('payments')->where('tenant_id', $tenantId)->where('reference', $reference)->exists();
                if (! $exists) {
                    $totalPaidKobo = (int) ($verified['data']['amount'] ?? ($metadata['total_amount_kobo'] ?? 0));
                    $feeAmountKobo = (int) ($metadata['fee_amount_kobo'] ?? ($metadata['amount_kobo'] ?? 0));
                    $serviceFeeKobo = (int) ($metadata['service_fee_kobo'] ?? 0);
                    $currency = (string) ($verified['data']['currency'] ?? 'NGN');
                    $paidAt = $verified['data']['paid_at'] ?? null;

                    DB::table('payments')->insert([
                        'tenant_id' => $tenantId,
                        'student_id' => (int) ($metadata['student_id'] ?? 0),
                        'class_id' => isset($metadata['class_id']) ? (int) $metadata['class_id'] : null,
                        'fee_rule_id' => isset($metadata['fee_rule_id']) ? (int) $metadata['fee_rule_id'] : null,
                        'academic_session_id' => isset($metadata['academic_session_id']) ? (int) $metadata['academic_session_id'] : null,
                        'term_id' => isset($metadata['term_id']) ? (int) $metadata['term_id'] : null,
                        'amount_kobo' => $feeAmountKobo,
                        'service_fee_kobo' => $serviceFeeKobo ?: null,
                        'total_paid_kobo' => $totalPaidKobo ?: null,
                        'currency' => $currency,
                        'label' => (string) ($metadata['label'] ?? 'School Fees'),
                        'reference' => $reference,
                        'status' => 'success',
                        'provider' => 'paystack',
                        'method' => 'automatic',
                        'provider_transaction_id' => $transactionId ? (string) $transactionId : null,
                        'provider_payload' => json_encode($verified),
                        'paid_at' => $paidAt ? \Carbon\Carbon::parse($paidAt) : now(),
                        'receipt_number' => 'RCPT-'.strtoupper(substr($reference, 0, 10)),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Bust student fee cache for current session (if present)
                $studentId = (int) ($metadata['student_id'] ?? 0);
                $sessionId = (int) ($metadata['academic_session_id'] ?? 0);
                if ($studentId && $sessionId) {
                    TenantCache::forgetStudentFees($school, $studentId, $sessionId);
                }

                // Notify school by email (fee amount only), once per reference
                $this->notifySchoolIfNeeded($school, $reference);
            }

            return response()->json(['ok' => true]);
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }
}


