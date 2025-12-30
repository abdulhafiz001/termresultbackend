<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', [
                'all_students_promote',
                'minimum_grades_required',
                'minimum_average_score',
                'minimum_subjects_passed',
            ])->index();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->json('criteria')->nullable(); // dynamic rule config (thresholds, min_grade, pass_mark, etc.)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('student_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_rule_id')->nullable()->constrained('promotion_rules')->nullOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();

            $table->foreignId('from_class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('to_class_id')->nullable()->constrained('classes')->nullOnDelete();

            $table->enum('status', ['promoted', 'repeated', 'graduated'])->index();
            $table->json('summary')->nullable(); // computed stats used for the decision (avg, passed_count, etc.)
            $table->timestamps();

            $table->unique(['student_id', 'academic_session_id', 'term_id'], 'uniq_student_promotion_term');
            $table->index(['academic_session_id', 'term_id', 'from_class_id'], 'idx_promotion_term_class');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_promotions');
        Schema::dropIfExists('promotion_rules');
    }
};


