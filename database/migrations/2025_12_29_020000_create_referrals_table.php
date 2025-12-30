<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->string('referrer_name');
            $table->string('referrer_phone', 50)->nullable();
            $table->string('referrer_email')->nullable();

            $table->string('school_name');
            $table->string('school_address')->nullable();
            $table->string('school_city')->nullable();
            $table->string('school_state')->nullable();

            $table->text('notes')->nullable();

            $table->string('referral_code', 20)->unique();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending')->index();

            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};


