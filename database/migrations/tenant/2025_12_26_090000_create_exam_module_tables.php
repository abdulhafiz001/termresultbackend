<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_question_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();

            $table->enum('exam_type', ['objective', 'theory', 'fill_blank'])->index();
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->unsignedSmallInteger('question_count')->nullable(); // required for theory/fill_blank (MVP)

            // Student-facing paper must be PDF.
            $table->string('paper_pdf_path'); // storage/public path

            // Optional teacher source for parsing objective questions (txt only for MVP) and admin download.
            $table->string('source_file_path')->nullable();
            $table->string('source_file_original_name')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); // admin
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['academic_session_id', 'term_id', 'class_id', 'subject_id'], 'idx_exam_sub_filter');
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('exam_question_submissions')->cascadeOnDelete();
            $table->string('code', 6)->unique();

            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();

            $table->enum('exam_type', ['objective', 'theory', 'fill_blank'])->index();
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->unsignedSmallInteger('question_count')->nullable();

            $table->enum('status', ['approved', 'live', 'ended'])->default('approved')->index();
            $table->timestamp('started_at')->nullable(); // set by admin
            $table->timestamp('ended_at')->nullable();   // set by admin

            $table->json('answer_key')->nullable(); // objective: { "1":"A", "2":"C" }
            $table->timestamps();

            $table->index(['academic_session_id', 'term_id', 'class_id'], 'idx_exams_filter');
        });

        Schema::create('exam_objective_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->unsignedSmallInteger('question_number');
            $table->text('question_text');
            $table->text('option_a')->nullable();
            $table->text('option_b')->nullable();
            $table->text('option_c')->nullable();
            $table->text('option_d')->nullable();
            $table->text('option_e')->nullable();
            $table->timestamps();

            $table->unique(['exam_id', 'question_number'], 'uniq_exam_qno');
        });

        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();

            $table->string('continue_token_hash', 64)->index(); // sha256 hash
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->enum('status', ['not_started', 'in_progress', 'submitted'])->default('not_started')->index();
            $table->unsignedSmallInteger('objective_score')->nullable();
            $table->unsignedSmallInteger('total_score')->nullable(); // for theory/fill marking

            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete(); // teacher
            $table->timestamp('marked_at')->nullable();

            $table->timestamps();

            $table->unique(['exam_id', 'student_id'], 'uniq_exam_student_attempt');
            $table->index(['exam_id', 'status'], 'idx_exam_attempt_status');
        });

        Schema::create('exam_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->unsignedSmallInteger('question_number')->nullable(); // null for objective single row? (we store per-question anyway)
            $table->string('objective_choice', 2)->nullable(); // A/B/C/D/E
            $table->longText('answer_text')->nullable(); // theory/fill
            $table->unsignedSmallInteger('mark')->nullable(); // manual mark per question
            $table->timestamps();

            $table->unique(['attempt_id', 'question_number'], 'uniq_attempt_qno');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempt_answers');
        Schema::dropIfExists('exam_attempts');
        Schema::dropIfExists('exam_objective_questions');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('exam_question_submissions');
    }
};


