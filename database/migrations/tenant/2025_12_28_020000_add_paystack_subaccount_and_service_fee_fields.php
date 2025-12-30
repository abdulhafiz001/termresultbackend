<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_settings', 'paystack_subaccount_code')) {
                $table->string('paystack_subaccount_code')->nullable()->after('paystack_account_name');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'service_fee_kobo')) {
                $table->unsignedInteger('service_fee_kobo')->nullable()->after('amount_kobo');
            }
            if (! Schema::hasColumn('payments', 'total_paid_kobo')) {
                $table->unsignedInteger('total_paid_kobo')->nullable()->after('service_fee_kobo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_settings', function (Blueprint $table) {
            if (Schema::hasColumn('payment_settings', 'paystack_subaccount_code')) {
                $table->dropColumn('paystack_subaccount_code');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'total_paid_kobo')) $table->dropColumn('total_paid_kobo');
            if (Schema::hasColumn('payments', 'service_fee_kobo')) $table->dropColumn('service_fee_kobo');
        });
    }
};


