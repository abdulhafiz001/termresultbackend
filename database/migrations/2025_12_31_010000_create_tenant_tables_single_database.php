<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // NOTE:
        // - These tables previously lived in per-school databases (database/migrations/tenant).
        // - In single-database mode, we create them once in the central DB and scope by tenant_id.
        // - tenant_id references `tenants.id` (string) generated from the School's id.

        $this->createAcademicSessionsAndTerms();
        $this->createClassesSubjectsAndPivots();
        $this->createStudentProfilesAndPivots();
        $this->createAnnouncements();
        $this->createScoresAttendance();
        $this->createStudyMaterialsComplaints();
        $this->createFeesAndPayments();
        $this->createTimetables();
        $this->createPromotionAndGrading();
        $this->createTeacherActivities();
        $this->createExamModule();
        $this->createAssignmentsModule();
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignments');

        Schema::dropIfExists('exam_attempt_answers');
        Schema::dropIfExists('exam_attempts');
        Schema::dropIfExists('exam_objective_questions');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('exam_question_submissions');

        Schema::dropIfExists('teacher_activities');

        Schema::dropIfExists('grading_config_ranges');
        Schema::dropIfExists('grading_config_classes');
        Schema::dropIfExists('grading_configs');
        Schema::dropIfExists('student_promotions');
        Schema::dropIfExists('promotion_rules');

        Schema::dropIfExists('timetables');

        Schema::dropIfExists('payment_settings');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('fee_rules');

        Schema::dropIfExists('complaints');
        Schema::dropIfExists('study_materials');

        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_sessions');
        Schema::dropIfExists('student_scores');

        Schema::dropIfExists('announcement_views');
        Schema::dropIfExists('announcements');

        Schema::dropIfExists('student_subject');
        Schema::dropIfExists('student_profiles');

        Schema::dropIfExists('teacher_subject');
        Schema::dropIfExists('teacher_class');
        Schema::dropIfExists('class_subject');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('classes');

        Schema::dropIfExists('terms');
        Schema::dropIfExists('academic_sessions');
    }

    private function addTenant(Blueprint $table): void
    {
        $table->string('tenant_id')->index();
        $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
    }

    private function createAcademicSessionsAndTerms(): void
    {
        if (! Schema::hasTable('academic_sessions')) {
            Schema::create('academic_sessions', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->string('name'); // e.g. 2024/2025
                $table->boolean('is_current')->default(false)->index();
                $table->timestamps();

                $table->unique(['tenant_id', 'name'], 'uniq_academic_sessions_tenant_name');
            });
        }

        if (! Schema::hasTable('terms')) {
            Schema::create('terms', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
                $table->string('name'); // First/Second/Third
                $table->boolean('is_current')->default(false)->index();
                $table->timestamps();

                $table->unique(['tenant_id', 'academic_session_id', 'name'], 'uniq_terms_tenant_session_name');
            });
        }
    }

    private function createClassesSubjectsAndPivots(): void
    {
        if (! Schema::hasTable('classes')) {
            Schema::create('classes', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->string('name');
                $table->foreignId('form_teacher_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('description')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'name'], 'uniq_classes_tenant_name');
            });
        }

        if (! Schema::hasTable('subjects')) {
            Schema::create('subjects', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->string('name');
                $table->string('code')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'name'], 'uniq_subjects_tenant_name');
                $table->unique(['tenant_id', 'code'], 'uniq_subjects_tenant_code');
            });
        }

        if (! Schema::hasTable('class_subject')) {
            Schema::create('class_subject', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
                $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['class_id', 'subject_id'], 'uniq_class_subject');
                $table->index(['tenant_id', 'class_id']);
            });
        }

        if (! Schema::hasTable('teacher_class')) {
            Schema::create('teacher_class', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['teacher_id', 'class_id'], 'uniq_teacher_class');
                $table->index(['tenant_id', 'teacher_id']);
            });
        }

        if (! Schema::hasTable('teacher_subject')) {
            Schema::create('teacher_subject', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['teacher_id', 'subject_id'], 'uniq_teacher_subject');
                $table->index(['tenant_id', 'teacher_id']);
            });
        }
    }

    private function createStudentProfilesAndPivots(): void
    {
        if (! Schema::hasTable('student_profiles')) {
            Schema::create('student_profiles', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('middle_name')->nullable();
                $table->string('gender')->nullable();
                $table->date('date_of_birth')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('address')->nullable();
                $table->foreignId('current_class_id')->nullable()->constrained('classes')->nullOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'current_class_id'], 'idx_student_profiles_tenant_class');
            });
        }

        if (! Schema::hasTable('student_subject')) {
            Schema::create('student_subject', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['student_id', 'subject_id'], 'uniq_student_subject');
                $table->index(['tenant_id', 'student_id']);
            });
        }
    }

    private function createAnnouncements(): void
    {
        if (! Schema::hasTable('announcements')) {
            Schema::create('announcements', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->string('title');
                $table->text('body');
                $table->timestamps();

                $table->index(['tenant_id', 'created_by']);
            });
        }

        if (! Schema::hasTable('announcement_views')) {
            Schema::create('announcement_views', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['announcement_id', 'user_id'], 'uniq_announcement_user_view');
                $table->index(['tenant_id', 'user_id']);
            });
        }
    }

    private function createScoresAttendance(): void
    {
        if (! Schema::hasTable('student_scores')) {
            Schema::create('student_scores', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
                $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
                $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
                $table->unsignedSmallInteger('ca_score')->nullable();
                $table->unsignedSmallInteger('exam_score')->nullable();
                $table->unsignedSmallInteger('total_score')->nullable();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['student_id', 'subject_id', 'academic_session_id', 'term_id'], 'uniq_student_subject_term');
                $table->index(['tenant_id', 'student_id']);
            });
        }

        if (! Schema::hasTable('attendance_sessions')) {
            Schema::create('attendance_sessions', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
                $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
                $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
                $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
                $table->date('date')->index();
                $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['class_id', 'subject_id', 'academic_session_id', 'term_id', 'date'], 'uniq_att_session');
                $table->index(['tenant_id', 'class_id']);
            });
        }

        if (! Schema::hasTable('attendance_records')) {
            Schema::create('attendance_records', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->foreignId('attendance_session_id')->constrained('attendance_sessions')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
                $table->boolean('present')->default(false)->index();
                $table->timestamps();

                $table->unique(['attendance_session_id', 'student_id'], 'uniq_att_student');
                $table->index(['tenant_id', 'student_id']);
            });
        }
    }

    private function createStudyMaterialsComplaints(): void
    {
        if (! Schema::hasTable('study_materials')) {
            Schema::create('study_materials', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
                $table->string('title');
                $table->string('file_path');
                $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
                $table->foreignId('academic_session_id')->nullable()->constrained('academic_sessions')->nullOnDelete();
                $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'uploaded_by']);
            });
        }

        if (! Schema::hasTable('complaints')) {
            Schema::create('complaints', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
                $table->string('subject');
                $table->text('message');
                $table->string('status', 30)->default('open')->index();
                $table->timestamps();

                $table->index(['tenant_id', 'student_id']);
            });
        }
    }

    private function createFeesAndPayments(): void
    {
        if (! Schema::hasTable('fee_rules')) {
            Schema::create('fee_rules', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
                $table->string('label');
                $table->unsignedInteger('amount_kobo')->default(0);
                $table->foreignId('academic_session_id')->nullable()->constrained('academic_sessions')->nullOnDelete();
                $table->timestamps();

                $table->unique(['class_id', 'label'], 'uniq_fee_rule_class_label');
                $table->index(['tenant_id', 'class_id']);
            });
        }

        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
                $table->foreignId('fee_rule_id')->nullable()->constrained('fee_rules')->nullOnDelete();
                $table->foreignId('academic_session_id')->nullable()->constrained('academic_sessions')->nullOnDelete();
                $table->string('reference');
                $table->unsignedInteger('amount_kobo')->default(0);
                $table->string('provider', 50)->nullable();
                $table->string('status', 30)->default('pending')->index();
                $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('school_notified_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'reference'], 'uniq_payments_tenant_reference');
                $table->index(['tenant_id', 'student_id']);
            });
        }

        if (! Schema::hasTable('payment_settings')) {
            Schema::create('payment_settings', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->boolean('enabled')->default(false);
                $table->string('mode', 30)->default('manual'); // manual|paystack
                $table->string('paystack_subaccount_code')->nullable();
                $table->unsignedInteger('service_fee_kobo')->default(0);
                $table->json('settlement')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id'], 'uniq_payment_settings_tenant');
            });
        }
    }

    private function createTimetables(): void
    {
        if (! Schema::hasTable('timetables')) {
            Schema::create('timetables', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
                $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
                $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedTinyInteger('day_of_week')->index(); // 1-7
                $table->time('start_time');
                $table->time('end_time');
                $table->timestamps();

                $table->unique(['teacher_id', 'day_of_week', 'start_time', 'end_time'], 'teacher_time_unique');
                $table->unique(['class_id', 'day_of_week', 'start_time', 'end_time'], 'class_time_unique');
                $table->index(['tenant_id', 'class_id']);
            });
        }
    }

    private function createPromotionAndGrading(): void
    {
        if (! Schema::hasTable('promotion_rules')) {
            Schema::create('promotion_rules', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->string('name');
                $table->foreignId('from_class_id')->constrained('classes')->cascadeOnDelete();
                $table->foreignId('to_class_id')->nullable()->constrained('classes')->nullOnDelete();
                $table->unsignedSmallInteger('min_average_score')->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'from_class_id']);
            });
        }

        if (! Schema::hasTable('student_promotions')) {
            Schema::create('student_promotions', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->foreignId('promotion_rule_id')->nullable()->constrained('promotion_rules')->nullOnDelete();
                $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
                $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
                $table->foreignId('from_class_id')->constrained('classes')->cascadeOnDelete();
                $table->foreignId('to_class_id')->nullable()->constrained('classes')->nullOnDelete();
                $table->string('status', 30)->default('promoted')->index();
                $table->timestamps();

                $table->unique(['student_id', 'academic_session_id', 'term_id'], 'uniq_student_promotion_term');
                $table->index(['tenant_id', 'student_id']);
            });
        }

        if (! Schema::hasTable('grading_configs')) {
            Schema::create('grading_configs', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->string('name');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'created_by']);
            });
        }

        if (! Schema::hasTable('grading_config_classes')) {
            Schema::create('grading_config_classes', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->foreignId('grading_config_id')->constrained('grading_configs')->cascadeOnDelete();
                $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['grading_config_id', 'class_id'], 'uniq_grading_cfg_class');
                $table->index(['tenant_id', 'class_id']);
            });
        }

        if (! Schema::hasTable('grading_config_ranges')) {
            Schema::create('grading_config_ranges', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->foreignId('grading_config_id')->constrained('grading_configs')->cascadeOnDelete();
                $table->string('grade', 10);
                $table->unsignedSmallInteger('min_score')->default(0);
                $table->unsignedSmallInteger('max_score')->default(0);
                $table->string('remark')->nullable();
                $table->timestamps();

                $table->unique(['grading_config_id', 'grade'], 'uniq_grading_cfg_grade');
            });
        }
    }

    private function createTeacherActivities(): void
    {
        if (! Schema::hasTable('teacher_activities')) {
            Schema::create('teacher_activities', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
                $table->string('action');
                $table->json('metadata')->nullable();
                $table->string('ip', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'teacher_id']);
            });
        }
    }

    private function createExamModule(): void
    {
        if (! Schema::hasTable('exam_question_submissions')) {
            Schema::create('exam_question_submissions', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
                $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
                $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
                $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
                $table->string('exam_type', 30)->index();
                $table->unsignedSmallInteger('duration_minutes')->default(60);
                $table->unsignedSmallInteger('question_count')->nullable();
                $table->string('paper_pdf_path');
                $table->string('source_file_path')->nullable();
                $table->string('source_file_original_name')->nullable();
                $table->string('status', 30)->default('pending')->index();
                $table->text('rejection_reason')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'academic_session_id', 'term_id', 'class_id', 'subject_id'], 'idx_exam_sub_filter_tenant');
            });
        }

        if (! Schema::hasTable('exams')) {
            Schema::create('exams', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->foreignId('submission_id')->constrained('exam_question_submissions')->cascadeOnDelete();
                $table->string('code', 6);
                $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
                $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
                $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
                $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
                $table->string('exam_type', 30)->index();
                $table->unsignedSmallInteger('duration_minutes')->default(60);
                $table->unsignedSmallInteger('question_count')->nullable();
                $table->string('status', 30)->default('approved')->index();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->json('answer_key')->nullable();
                $table->unsignedSmallInteger('marks_per_question')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'code'], 'uniq_exams_tenant_code');
                $table->index(['tenant_id', 'academic_session_id', 'term_id', 'class_id'], 'idx_exams_filter_tenant');
            });
        }

        if (! Schema::hasTable('exam_objective_questions')) {
            Schema::create('exam_objective_questions', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
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
                $table->index(['tenant_id', 'exam_id']);
            });
        }

        if (! Schema::hasTable('exam_attempts')) {
            Schema::create('exam_attempts', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
                $table->string('continue_token_hash', 64)->index();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->string('status', 30)->default('not_started')->index();
                $table->unsignedSmallInteger('objective_score')->nullable();
                $table->unsignedSmallInteger('total_score')->nullable();
                $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('marked_at')->nullable();
                $table->string('continue_key_plain')->nullable();
                $table->timestamps();

                $table->unique(['exam_id', 'student_id'], 'uniq_exam_student_attempt');
                $table->index(['exam_id', 'status'], 'idx_exam_attempt_status');
                $table->index(['tenant_id', 'student_id']);
            });
        }

        if (! Schema::hasTable('exam_attempt_answers')) {
            Schema::create('exam_attempt_answers', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->foreignId('attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
                $table->unsignedSmallInteger('question_number')->nullable();
                $table->string('objective_choice', 2)->nullable();
                $table->longText('answer_text')->nullable();
                $table->unsignedSmallInteger('mark')->nullable();
                $table->timestamps();

                $table->unique(['attempt_id', 'question_number'], 'uniq_attempt_qno');
                $table->index(['tenant_id', 'attempt_id']);
            });
        }
    }

    private function createAssignmentsModule(): void
    {
        if (! Schema::hasTable('assignments')) {
            Schema::create('assignments', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->string('assignment_number');
                $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
                $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
                $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
                $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->timestamp('due_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'assignment_number'], 'uniq_assignments_tenant_number');
                $table->index(['tenant_id', 'class_id']);
            });
        }

        if (! Schema::hasTable('assignment_submissions')) {
            Schema::create('assignment_submissions', function (Blueprint $table) {
                $table->id();
                $this->addTenant($table);
                $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
                $table->text('submission_text')->nullable();
                $table->string('file_path')->nullable();
                $table->unsignedSmallInteger('mark')->nullable();
                $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('marked_at')->nullable();
                $table->timestamps();

                $table->unique(['assignment_id', 'student_id'], 'uniq_assignment_student');
                $table->index(['tenant_id', 'student_id']);
            });
        }
    }
};



