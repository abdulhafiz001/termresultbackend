<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('action')->index(); // e.g. teacher_login, score_saved, attendance_saved
            $table->json('metadata')->nullable();
            $table->string('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['teacher_id', 'created_at'], 'idx_teacher_activity_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_activities');
    }
};


