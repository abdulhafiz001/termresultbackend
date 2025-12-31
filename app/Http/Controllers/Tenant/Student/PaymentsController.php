<?php

namespace App\Http\Controllers\Tenant\Student;

use App\Http\Controllers\Controller;
use App\Mail\SchoolPaymentReceived;
use App\Services\PaystackService;
use App\Support\TenantCache;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PaymentsController extends Controller
{
    private function notifySchoolIfNeeded(string $reference): void
    {
        $school = app('tenant.school');
        if (! $school || empty($school->contact_email)) return;

        if (! \Schema::hasColumn('payments', 'school_notified_at')) return;

        $payment = TenantDB::table('payments')->where('reference', $reference)->first();
        if (! $payment || ($payment->status ?? null) !== 'success') return;
        if (! empty($payment->school_notified_at)) return;

        try {
            $student = TenantDB::table('users')->where('id', (int) $payment->student_id)->first();
            $profile = TenantDB::table('student_profiles')->where('user_id', (int) $payment->student_id)->first();
            $class = $payment->class_id ? TenantDB::table('classes')->where('id', (int) $payment->class_id)->first() : null;

            Mail::to((string) $school->contact_email)->send(new SchoolPaymentReceived($school, $payment, $student, $profile, $class));

            TenantDB::table('payments')->where('id', (int) $payment->id)->update([
                'school_notified_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Don't break payment flow for email errors.
            \Log::error('Failed to send school payment notification email.', [
                'reference' => $reference,
                'school_id' => $school?->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function serviceFeeKobo(int $feeAmountKobo): int
    {
        // Service charge tiers (in Naira) converted to Kobo:
        // < 10,000 => 100
        // >= 10,000 => 200
        // >= 35,000 => 300
        // >= 55,000 => 500
        $amountNaira = (int) floor($feeAmountKobo / 100);

        if ($amountNaira < 10000) return 100 * 100;
        if ($amountNaira < 35000) return 200 * 100;
        if ($amountNaira < 55000) return 300 * 100;
        return 500 * 100;
    }

    public function feeSummary(Request $request)
    {
        $studentId = $request->user()->id;
        $profile = TenantDB::table('student_profiles')->where('user_id', $studentId)->first();
        $classId = $profile?->current_class_id;

        if (! $classId) {
            return response()->json(['message' => 'Student class is not set.'], 400);
        }

        $filter = $request->validate([
            'academic_session_id' => ['nullable', 'integer'],
            'term_id' => ['nullable', 'integer'],
        ]);

        $currentSession = TenantDB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? TenantDB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;

        $sessionId = isset($filter['academic_session_id']) ? (int) $filter['academic_session_id'] : (int) ($currentSession?->id ?? 0);
        $termId = isset($filter['term_id']) ? (int) $filter['term_id'] : (int) ($currentTerm?->id ?? 0);

        $school = app('tenant.school');
        $schoolId = (int) ($school?->id ?? 0);
        // Include term in cache key to avoid mixing payments across terms.
        $cacheKey = TenantCache::studentFeesKey($schoolId, (int) $studentId, (int) ($sessionId ?? 0)) . ':term=' . (int) ($termId ?? 0);

        $payload = Cache::remember($cacheKey, 60, function () use ($classId, $currentSession, $currentTerm, $sessionId, $termId, $studentId) {
            $rulesQuery = TenantDB::table('fee_rules')->where('class_id', $classId);
            // Fee rules may be global (null session) or session-scoped.
            if ($sessionId) $rulesQuery->where(function ($w) use ($sessionId) {
                $w->whereNull('academic_session_id')->orWhere('academic_session_id', $sessionId);
            });
            $rules = $rulesQuery->orderBy('id')->get();

            $paymentsQuery = TenantDB::table('payments')
                ->where('student_id', $studentId)
                ->where('status', 'success')
                ->orderByDesc('paid_at')
                ->limit(100);

            // Payments must be EXACTLY scoped to the selected session/term to avoid mixing across terms.
            if ($sessionId && \Schema::hasColumn('payments', 'academic_session_id')) {
                $paymentsQuery->where('academic_session_id', $sessionId);
            }
            if ($termId && \Schema::hasColumn('payments', 'term_id')) {
                $paymentsQuery->where('term_id', $termId);
            }

            $payments = $paymentsQuery->get();

            $paidByRule = $payments->groupBy('fee_rule_id')->map(fn ($rows) => (int) $rows->sum('amount_kobo'));

            $rules = $rules->map(function ($r) use ($paidByRule) {
                $paid = (int) ($paidByRule[$r->id] ?? 0);
                $due = (int) $r->amount_kobo;
                $balance = max(0, $due - $paid);
                return (object) array_merge((array) $r, [
                    'paid_kobo' => $paid,
                    'balance_kobo' => $balance,
                ]);
            });

            $settings = TenantDB::table('payment_settings')->orderByDesc('id')->first();
            $paymentMode = $settings->mode ?? 'manual';
            // Only consider disabled if settings exist and are explicitly disabled
            $paymentEnabled = $settings ? (bool) ($settings->is_enabled ?? false) : true;

            return [
                'class_id' => $classId,
                'current_session' => $currentSession,
                'current_term' => $currentTerm,
                'filters' => [
                    'academic_session_id' => $sessionId ?: null,
                    'term_id' => $termId ?: null,
                ],
                'payment_settings' => [
                    'mode' => $paymentMode,
                    'is_enabled' => $paymentEnabled,
                    'has_settings' => $settings !== null,
                    'manual' => [
                        'school_account_name' => $settings->school_account_name ?? null,
                        'school_account_number' => $settings->school_account_number ?? null,
                        'school_bank_name' => $settings->school_bank_name ?? null,
                        'school_finance_phone' => $settings->school_finance_phone ?? null,
                    ],
                ],
                'rules' => $rules,
                'payments' => $payments,
            ];
        });

        return response()->json($payload);
    }

    public function initialize(Request $request, PaystackService $paystack)
    {
        $tenantId = TenantContext::id();

        $student = $request->user();
        $profile = TenantDB::table('student_profiles')->where('user_id', $student->id)->first();
        $classId = $profile?->current_class_id;

        $data = $request->validate([
            'fee_rule_id' => ['required', 'integer', 'exists:fee_rules,id'],
            'amount_naira' => ['required', 'numeric', 'min:1'],
            'callback_url' => ['nullable', 'string', 'max:500'],
        ]);

        if (! $classId) {
            return response()->json(['message' => 'Student class is not set.'], 400);
        }

        $rule = TenantDB::table('fee_rules')
            ->where('id', (int) $data['fee_rule_id'])
            ->where('class_id', $classId)
            ->first();

        if (! $rule) {
            return response()->json(['message' => 'Fee rule not found for your class.'], 404);
        }

        $amountKobo = (int) round(((float) $data['amount_naira']) * 100);
        if ($amountKobo <= 0) {
            return response()->json(['message' => 'Invalid amount.'], 422);
        }

        // Enforce remaining balance (no overpayment)
        $alreadyPaid = (int) TenantDB::table('payments')
            ->where('student_id', $student->id)
            ->where('fee_rule_id', (int) $rule->id)
            ->where('status', 'success')
            ->sum('amount_kobo');
        $remaining = max(0, (int) $rule->amount_kobo - $alreadyPaid);
        if ($remaining <= 0) {
            return response()->json(['message' => 'This fee is already fully paid.'], 422);
        }
        if ($amountKobo > $remaining) {
            return response()->json(['message' => 'Amount exceeds your remaining balance.'], 422);
        }

        $school = app('tenant.school');
        $reference = 'TR_'.Str::upper(Str::random(10)).'_'.time();

        $settings = TenantDB::table('payment_settings')->orderByDesc('id')->first();
        $mode = $settings->mode ?? 'manual';
        // Only check enabled if settings exist; if no settings, allow payments
        $enabled = $settings ? (bool) ($settings->is_enabled ?? false) : true;

        if (! $enabled) {
            return response()->json(['message' => 'Payments are currently disabled by the school.'], 403);
        }

        if ($mode === 'manual') {
            return response()->json([
                'mode' => 'manual',
                'reference' => $reference,
                'amount_kobo' => $amountKobo,
                'currency' => $rule->currency,
                'label' => $rule->label,
                'manual' => [
                    'school_account_name' => $settings->school_account_name ?? null,
                    'school_account_number' => $settings->school_account_number ?? null,
                    'school_bank_name' => $settings->school_bank_name ?? null,
                    'school_finance_phone' => $settings->school_finance_phone ?? null,
                ],
            ]);
        }

        // automatic (paystack)
        $currentSession = TenantDB::table('academic_sessions')->where('is_current', true)->first();
        $currentTerm = $currentSession
            ? TenantDB::table('terms')->where('academic_session_id', $currentSession->id)->where('is_current', true)->first()
            : null;
        $sessionId = $currentSession?->id;
        $termId = $currentTerm?->id;

        if (empty($settings?->paystack_subaccount_code)) {
            return response()->json(['message' => 'Paystack subaccount code is not configured for this school.'], 422);
        }

        $serviceFeeKobo = $this->serviceFeeKobo($amountKobo);
        $totalPayableKobo = $amountKobo + $serviceFeeKobo;

        $payload = [
            'email' => $student->email ?: ($profile->email ?? 'student@'.$school->subdomain.'.termresult.com'),
            // Student sees total payable (fee + TermResult service charge)
            'amount' => (int) $totalPayableKobo,
            'reference' => $reference,
            'currency' => $rule->currency,
            'callback_url' => $data['callback_url'] ?? null,
            // Split settlement: send school fee to school's subaccount, keep service fee in TermResult account.
            'subaccount' => (string) $settings->paystack_subaccount_code,
            'transaction_charge' => (int) $serviceFeeKobo,
            'metadata' => [
                'school_subdomain' => $school->subdomain,
                'student_id' => $student->id,
                'class_id' => $classId,
                'academic_session_id' => $sessionId,
                'term_id' => $termId,
                'fee_rule_id' => (int) $rule->id,
                'label' => $rule->label,
                'fee_amount_kobo' => (int) $amountKobo,
                'service_fee_kobo' => (int) $serviceFeeKobo,
                'total_amount_kobo' => (int) $totalPayableKobo,
            ],
        ];

        // Remove null callback_url because Paystack rejects some nulls
        if (empty($payload['callback_url'])) unset($payload['callback_url']);

        // Use TermResult Paystack key (env PAYSTACK_SECRET_KEY) for split payments
        $resp = $paystack->initialize($payload);

        return response()->json([
            'mode' => 'automatic',
            'reference' => $reference,
            'authorization_url' => $resp['data']['authorization_url'] ?? null,
            'access_code' => $resp['data']['access_code'] ?? null,
            'fee_amount_kobo' => (int) $amountKobo,
            'service_fee_kobo' => (int) $serviceFeeKobo,
            'total_amount_kobo' => (int) $totalPayableKobo,
        ]);
    }

    public function receipt(Request $request, int $id)
    {
        $payment = TenantDB::table('payments')->where('id', $id)->where('student_id', $request->user()->id)->first();
        if (! $payment) {
            return response()->json(['message' => 'Payment not found.'], 404);
        }
        if ($payment->status !== 'success') {
            return response()->json(['message' => 'Receipt available only for successful payments.'], 400);
        }

        $studentId = $request->user()->id;
        $profile = TenantDB::table('student_profiles')->where('user_id', $studentId)->first();
        $class = $profile?->current_class_id ? TenantDB::table('classes')->where('id', $profile->current_class_id)->first() : null;
        $school = app('tenant.school');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf/receipt', [
            'payment' => $payment,
            'student' => $request->user(),
            'profile' => $profile,
            'class' => $class,
            'school' => $school,
        ]);

        return $pdf->download('receipt-'.$payment->reference.'.pdf');
    }

    public function confirm(Request $request, PaystackService $paystack)
    {
        $tenantId = TenantContext::id();

        $student = $request->user();
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100'],
        ]);

        $settings = TenantDB::table('payment_settings')->orderByDesc('id')->first();
        if (! $settings || ($settings->mode ?? 'manual') !== 'automatic') {
            return response()->json(['message' => 'Automatic payments are not configured for this school.'], 422);
        }

        // Verify via TermResult Paystack key (env PAYSTACK_SECRET_KEY)
        $verified = $paystack->verify($data['reference']);
        $status = $verified['data']['status'] ?? null;
        if ($status !== 'success') {
            return response()->json(['message' => 'Payment is not successful.'], 422);
        }

        $metadata = $verified['data']['metadata'] ?? [];
        if ((int) ($metadata['student_id'] ?? 0) !== (int) $student->id) {
            return response()->json(['message' => 'This payment does not belong to the current student.'], 403);
        }

        $reference = (string) ($verified['data']['reference'] ?? $data['reference']);
        $exists = TenantDB::table('payments')->where('reference', $reference)->exists();
        if (! $exists) {
            $totalPaidKobo = (int) ($verified['data']['amount'] ?? ($metadata['total_amount_kobo'] ?? 0));
            $feeAmountKobo = (int) ($metadata['fee_amount_kobo'] ?? ($metadata['amount_kobo'] ?? 0));
            $serviceFeeKobo = (int) ($metadata['service_fee_kobo'] ?? 0);

            if ($feeAmountKobo <= 0) {
                return response()->json(['message' => 'Invalid payment metadata.'], 422);
            }
            if ($serviceFeeKobo < 0) $serviceFeeKobo = 0;

            // Basic validation: total paid should cover fee + service charge
            if ($totalPaidKobo > 0 && ($feeAmountKobo + $serviceFeeKobo) !== $totalPaidKobo) {
                return response()->json(['message' => 'Payment amount mismatch. Please contact support.'], 422);
            }

            $currency = (string) ($verified['data']['currency'] ?? 'NGN');
            $transactionId = $verified['data']['id'] ?? null;
            $paidAt = $verified['data']['paid_at'] ?? null;

            DB::table('payments')->insert([
                'tenant_id' => $tenantId,
                'student_id' => (int) $student->id,
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

        // Bust student fee cache for current session
        $school = app('tenant.school');
        $sessionId = (int) ($metadata['academic_session_id'] ?? 0);
        if ($school && $sessionId) {
            TenantCache::forgetStudentFees($school, (int) $student->id, $sessionId);
        }

        // Notify school by email (fee amount only), once per reference
        $this->notifySchoolIfNeeded($reference);

        return response()->json(['ok' => true]);
    }
}


