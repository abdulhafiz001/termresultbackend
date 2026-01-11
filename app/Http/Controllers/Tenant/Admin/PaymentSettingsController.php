<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Services\PaystackService;
use App\Support\TenantContext;
use App\Support\TenantDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentSettingsController extends Controller
{
    public function show()
    {
        $row = TenantDB::table('payment_settings')->orderByDesc('id')->first();
        $accountNumberLast4 = null;
        if (! empty($row?->paystack_settlement_account_number_enc)) {
            try {
                $plain = Crypt::decryptString($row->paystack_settlement_account_number_enc);
                $accountNumberLast4 = substr($plain, -4);
            } catch (\Throwable $e) {
                $accountNumberLast4 = null;
            }
        }

        return response()->json([
            'data' => [
                'mode' => $row->mode ?? 'manual',
                'is_enabled' => (bool) ($row->is_enabled ?? false),
                'manual' => [
                    'school_account_name' => $row->school_account_name ?? null,
                    'school_account_number' => $row->school_account_number ?? null,
                    'school_bank_name' => $row->school_bank_name ?? null,
                    'school_finance_phone' => $row->school_finance_phone ?? null,
                ],
                'automatic' => [
                    'account_name' => $row->account_name ?? null,
                    'paystack_account_name' => $row->paystack_account_name ?? null,
                    'paystack_subaccount_code' => $row->paystack_subaccount_code ?? null,
                    'settlement_bank_code' => $row->paystack_settlement_bank_code ?? null,
                    'settlement_bank_name' => $row->paystack_settlement_bank_name ?? null,
                    'settlement_account_name' => $row->paystack_settlement_account_name ?? null,
                    'settlement_account_last4' => $accountNumberLast4,
                ],
            ],
        ]);
    }

    public function paystackBanks(PaystackService $paystack)
    {
        try {
            $json = $paystack->listBanks('nigeria');
            $rows = $json['data'] ?? [];
            $banks = collect($rows)->map(fn ($b) => [
                'name' => $b['name'] ?? null,
                'code' => $b['code'] ?? null,
                'slug' => $b['slug'] ?? null,
            ])->filter(fn ($b) => ! empty($b['code']) && ! empty($b['name']))->values()->all();

            return response()->json(['data' => $banks]);
        } catch (\Throwable $e) {
            Log::error('Paystack banks API error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return response()->json([
                'message' => 'Failed to load banks. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function resolvePaystackAccount(Request $request, PaystackService $paystack)
    {
        $data = $request->validate([
            'bank_code' => ['required', 'string', 'max:20'],
            'account_number' => ['required', 'string', 'max:20'],
        ]);

        $json = $paystack->resolveBankAccount($data['account_number'], $data['bank_code']);
        $acctName = $json['data']['account_name'] ?? null;
        if (! $acctName) {
            return response()->json(['message' => 'Could not resolve account name.'], 422);
        }

        return response()->json([
            'data' => [
                'account_name' => $acctName,
            ],
        ]);
    }

    public function createPaystackSubaccount(Request $request, PaystackService $paystack)
    {
        $tenantId = TenantContext::id();

        $data = $request->validate([
            'bank_code' => ['required', 'string', 'max:20'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:20'],
            'business_name' => ['nullable', 'string', 'max:255'],
        ]);

        // Resolve account name from Paystack (prevents wrong bank details)
        $resolved = $paystack->resolveBankAccount($data['account_number'], $data['bank_code']);
        $accountName = $resolved['data']['account_name'] ?? null;
        if (! $accountName) {
            return response()->json(['message' => 'Could not resolve account name. Please check the bank and account number.'], 422);
        }

        $existing = TenantDB::table('payment_settings')->orderByDesc('id')->first();
        $businessName = $data['business_name'] ?? (app('tenant.school')->name ?? 'School');

        // Create subaccount via TermResult Paystack (main) account
        $created = $paystack->createSubaccount([
            'business_name' => $businessName,
            'settlement_bank' => $data['bank_code'],
            'account_number' => $data['account_number'],
            // We use fixed service fees via transaction_charge; no percentage deduction to TermResult
            'percentage_charge' => 0,
        ]);

        $code = $created['data']['subaccount_code'] ?? null;
        if (! $code) {
            return response()->json(['message' => 'Failed to create subaccount.'], 500);
        }

        $payload = [
            'paystack_subaccount_code' => $code,
            'paystack_settlement_bank_code' => $data['bank_code'],
            'paystack_settlement_bank_name' => $data['bank_name'] ?? null,
            'paystack_settlement_account_number_enc' => Crypt::encryptString($data['account_number']),
            'paystack_settlement_account_name' => $accountName,
            'updated_at' => now(),
        ];

        if ($existing) {
            TenantDB::table('payment_settings')->where('id', $existing->id)->update($payload);
        } else {
            DB::table('payment_settings')->insert(array_merge([
                'tenant_id' => $tenantId,
                'mode' => 'automatic',
                'is_enabled' => false,
                'created_at' => now(),
            ], $payload));
        }

        return response()->json([
            'message' => 'Paystack subaccount created successfully.',
            'data' => [
                'subaccount_code' => $code,
                'settlement_account_name' => $accountName,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $tenantId = TenantContext::id();

        $data = $request->validate([
            'mode' => ['required', 'in:manual,automatic'],
            'is_enabled' => ['required', 'boolean'],

            // manual
            'school_account_name' => ['nullable', 'string', 'max:255'],
            'school_account_number' => ['nullable', 'string', 'max:50'],
            'school_bank_name' => ['nullable', 'string', 'max:255'],
            'school_finance_phone' => ['nullable', 'string', 'max:50'],

            // automatic
            'account_name' => ['nullable', 'string', 'max:255'],
            'paystack_account_name' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['mode'] === 'manual') {
            foreach (['school_account_name', 'school_account_number', 'school_bank_name', 'school_finance_phone'] as $k) {
                if (empty($data[$k])) {
                    return response()->json(['message' => 'Manual payment requires school account details.'], 422);
                }
            }
        }

        $existing = TenantDB::table('payment_settings')->orderByDesc('id')->first();

        if ($data['mode'] === 'automatic') {
            foreach (['account_name', 'paystack_account_name'] as $k) {
                if (empty($data[$k])) {
                    return response()->json(['message' => 'Automatic payment requires account name and Paystack account name.'], 422);
                }
            }
            // Subaccount is created via the dedicated endpoint (banks + resolve flow)
            if (empty($existing?->paystack_subaccount_code)) {
                return response()->json(['message' => 'Please create/verify Paystack settlement account to generate a subaccount code.'], 422);
            }
        }

        $payload = [
            'mode' => $data['mode'],
            'is_enabled' => (bool) $data['is_enabled'],

            'school_account_name' => $data['school_account_name'] ?? null,
            'school_account_number' => $data['school_account_number'] ?? null,
            'school_bank_name' => $data['school_bank_name'] ?? null,
            'school_finance_phone' => $data['school_finance_phone'] ?? null,

            'account_name' => $data['account_name'] ?? null,
            'paystack_account_name' => $data['paystack_account_name'] ?? null,
            'paystack_subaccount_code' => $existing->paystack_subaccount_code ?? null,
            'updated_at' => now(),
        ];

        if ($existing) {
            TenantDB::table('payment_settings')->where('id', $existing->id)->update($payload);
        } else {
            DB::table('payment_settings')->insert(array_merge($payload, [
                'tenant_id' => $tenantId,
                'created_at' => now(),
            ]));
        }

        return response()->json(['message' => 'Payment settings saved.']);
    }
}


