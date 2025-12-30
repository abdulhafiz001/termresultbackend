<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('mode', ['manual', 'automatic'])->default('manual');
            $table->boolean('is_enabled')->default(false);

            // Manual payment info
            $table->string('school_account_name')->nullable();
            $table->string('school_account_number')->nullable();
            $table->string('school_bank_name')->nullable();
            $table->string('school_finance_phone')->nullable();

            // Automatic (Paystack) info - encrypted at rest
            $table->string('account_name')->nullable();
            $table->string('paystack_account_name')->nullable();
            $table->text('paystack_public_key_enc')->nullable();
            $table->text('paystack_secret_key_enc')->nullable();

            $table->timestamps();
        });

        Schema::table('fee_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('fee_rules', 'academic_session_id')) {
                $table->foreignId('academic_session_id')->nullable()->after('class_id')->constrained('academic_sessions')->nullOnDelete();
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'fee_rule_id')) {
                $table->foreignId('fee_rule_id')->nullable()->after('class_id')->constrained('fee_rules')->nullOnDelete();
            }
            if (! Schema::hasColumn('payments', 'academic_session_id')) {
                $table->foreignId('academic_session_id')->nullable()->after('fee_rule_id')->constrained('academic_sessions')->nullOnDelete();
            }
            if (! Schema::hasColumn('payments', 'recorded_by_user_id')) {
                $table->foreignId('recorded_by_user_id')->nullable()->after('provider')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('payments', 'method')) {
                $table->enum('method', ['manual', 'automatic'])->default('automatic')->after('provider');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'method')) $table->dropColumn('method');
            if (Schema::hasColumn('payments', 'recorded_by_user_id')) $table->dropConstrainedForeignId('recorded_by_user_id');
            if (Schema::hasColumn('payments', 'academic_session_id')) $table->dropConstrainedForeignId('academic_session_id');
            if (Schema::hasColumn('payments', 'fee_rule_id')) $table->dropConstrainedForeignId('fee_rule_id');
        });

        Schema::table('fee_rules', function (Blueprint $table) {
            if (Schema::hasColumn('fee_rules', 'academic_session_id')) $table->dropConstrainedForeignId('academic_session_id');
        });

        Schema::dropIfExists('payment_settings');
    }
};


