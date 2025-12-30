<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_settings', function (Blueprint $table) {
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
        Schema::table('payment_settings', function (Blueprint $table) {
            if (Schema::hasColumn('payment_settings', 'paystack_settlement_account_name')) {
                $table->dropColumn('paystack_settlement_account_name');
            }
            if (Schema::hasColumn('payment_settings', 'paystack_settlement_account_number_enc')) {
                $table->dropColumn('paystack_settlement_account_number_enc');
            }
            if (Schema::hasColumn('payment_settings', 'paystack_settlement_bank_name')) {
                $table->dropColumn('paystack_settlement_bank_name');
            }
            if (Schema::hasColumn('payment_settings', 'paystack_settlement_bank_code')) {
                $table->dropColumn('paystack_settlement_bank_code');
            }
        });
    }
};


