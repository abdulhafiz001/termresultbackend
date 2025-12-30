<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_admin_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_admin_id')->constrained('platform_admins')->cascadeOnDelete();
            $table->string('action', 100)->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->string('subject_type', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_admin_activities');
    }
};


