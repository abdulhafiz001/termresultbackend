<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_kobo'); // NGN in kobo
            $table->string('currency', 10)->default('NGN');
            $table->string('label')->default('School Fees');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['class_id', 'label']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->unsignedBigInteger('amount_kobo');
            $table->string('currency', 10)->default('NGN');
            $table->string('label')->default('School Fees');

            $table->string('reference')->unique();
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending')->index();

            $table->string('provider', 50)->default('paystack');
            $table->string('provider_transaction_id')->nullable();
            $table->json('provider_payload')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->string('receipt_number')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('fee_rules');
    }
};


