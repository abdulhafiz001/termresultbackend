<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // fee_rules columns expected by controllers
        Schema::table('fee_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('fee_rules', 'currency')) {
                $table->string('currency', 10)->default('NGN')->after('amount_kobo');
            }
            if (! Schema::hasColumn('fee_rules', 'description')) {
                $table->text('description')->nullable()->after('label');
            }
        });

        // payments columns expected by controllers
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'currency')) {
                $table->string('currency', 10)->default('NGN')->after('amount_kobo');
            }
            if (! Schema::hasColumn('payments', 'label')) {
                $table->string('label')->default('School Fees')->after('currency');
            }
            if (! Schema::hasColumn('payments', 'method')) {
                $table->string('method', 30)->default('automatic')->after('provider');
            }
            if (! Schema::hasColumn('payments', 'provider_transaction_id')) {
                $table->string('provider_transaction_id')->nullable()->after('provider');
            }
            if (! Schema::hasColumn('payments', 'provider_payload')) {
                $table->json('provider_payload')->nullable()->after('provider_transaction_id');
            }
            if (! Schema::hasColumn('payments', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('provider_payload');
            }
            if (! Schema::hasColumn('payments', 'receipt_number')) {
                $table->string('receipt_number')->nullable()->after('paid_at');
            }
            if (! Schema::hasColumn('payments', 'service_fee_kobo')) {
                $table->unsignedInteger('service_fee_kobo')->nullable()->after('amount_kobo');
            }
            if (! Schema::hasColumn('payments', 'total_paid_kobo')) {
                $table->unsignedInteger('total_paid_kobo')->nullable()->after('service_fee_kobo');
            }
        });

        // payment_settings columns expected by controllers
        Schema::table('payment_settings', function (Blueprint $table) {
            // Migration originally created `enabled` - keep it, but add the expected `is_enabled`.
            if (! Schema::hasColumn('payment_settings', 'is_enabled')) {
                $table->boolean('is_enabled')->default(false)->after('mode');
            }

            // Manual payment info
            if (! Schema::hasColumn('payment_settings', 'school_account_name')) {
                $table->string('school_account_name')->nullable()->after('is_enabled');
            }
            if (! Schema::hasColumn('payment_settings', 'school_account_number')) {
                $table->string('school_account_number')->nullable()->after('school_account_name');
            }
            if (! Schema::hasColumn('payment_settings', 'school_bank_name')) {
                $table->string('school_bank_name')->nullable()->after('school_account_number');
            }
            if (! Schema::hasColumn('payment_settings', 'school_finance_phone')) {
                $table->string('school_finance_phone')->nullable()->after('school_bank_name');
            }

            // Automatic (Paystack) info - encrypted at rest
            if (! Schema::hasColumn('payment_settings', 'account_name')) {
                $table->string('account_name')->nullable()->after('school_finance_phone');
            }
            if (! Schema::hasColumn('payment_settings', 'paystack_account_name')) {
                $table->string('paystack_account_name')->nullable()->after('account_name');
            }
            if (! Schema::hasColumn('payment_settings', 'paystack_public_key_enc')) {
                $table->text('paystack_public_key_enc')->nullable()->after('paystack_account_name');
            }
            if (! Schema::hasColumn('payment_settings', 'paystack_secret_key_enc')) {
                $table->text('paystack_secret_key_enc')->nullable()->after('paystack_public_key_enc');
            }
            if (! Schema::hasColumn('payment_settings', 'paystack_subaccount_code')) {
                $table->string('paystack_subaccount_code')->nullable()->after('paystack_secret_key_enc');
            }
            if (! Schema::hasColumn('payment_settings', 'paystack_settlement_bank_code')) {
                $table->string('paystack_settlement_bank_code', 20)->nullable()->after('paystack_subaccount_code');
            }
            if (! Schema::hasColumn('payment_settings', 'paystack_settlement_bank_name')) {
                $table->string('paystack_settlement_bank_name', 255)->nullable()->after('paystack_settlement_bank_code');
            }
            if (! Schema::hasColumn('payment_settings', 'paystack_settlement_account_number_enc')) {
                $table->text('paystack_settlement_account_number_enc')->nullable()->after('paystack_settlement_bank_name');
            }
            if (! Schema::hasColumn('payment_settings', 'paystack_settlement_account_name')) {
                $table->string('paystack_settlement_account_name', 255)->nullable()->after('paystack_settlement_account_number_enc');
            }
        });
    }

    public function down(): void
    {
        // Best-effort: we won't drop columns on rollback to avoid data loss in production.
    }
};



