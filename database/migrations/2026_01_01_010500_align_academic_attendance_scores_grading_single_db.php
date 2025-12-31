<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Academic sessions/terms: add date ranges used by UI and progress endpoints.
        Schema::table('academic_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('academic_sessions', 'start_date')) {
                $table->date('start_date')->nullable()->after('name');
            }
            if (! Schema::hasColumn('academic_sessions', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
        });

        Schema::table('terms', function (Blueprint $table) {
            if (! Schema::hasColumn('terms', 'start_date')) {
                $table->date('start_date')->nullable()->after('name');
            }
            if (! Schema::hasColumn('terms', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
        });

        // Attendance: align with tenant migrations.
        Schema::table('attendance_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_sessions', 'week')) {
                $table->unsignedSmallInteger('week')->nullable()->after('date');
            }
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_records', 'status')) {
                $table->string('status', 20)->default('present')->after('student_id');
            }
        });

        // Scores: align with tenant migrations.
        Schema::table('student_scores', function (Blueprint $table) {
            if (! Schema::hasColumn('student_scores', 'ca1')) {
                $table->unsignedTinyInteger('ca1')->nullable()->after('term_id');
            }
            if (! Schema::hasColumn('student_scores', 'ca2')) {
                $table->unsignedTinyInteger('ca2')->nullable()->after('ca1');
            }
            if (! Schema::hasColumn('student_scores', 'exam')) {
                $table->unsignedTinyInteger('exam')->nullable()->after('ca2');
            }
            if (! Schema::hasColumn('student_scores', 'total')) {
                $table->unsignedTinyInteger('total')->nullable()->after('exam');
            }
            if (! Schema::hasColumn('student_scores', 'grade')) {
                $table->string('grade')->nullable()->after('total');
            }
            if (! Schema::hasColumn('student_scores', 'remark')) {
                $table->string('remark')->nullable()->after('grade');
            }
            if (! Schema::hasColumn('student_scores', 'recorded_by')) {
                $table->foreignId('recorded_by')->nullable()->after('remark')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('student_scores', 'recorded_at')) {
                $table->timestamp('recorded_at')->nullable()->after('recorded_by');
            }
        });

        // Grading configs: align with tenant migrations used by teacher UI.
        Schema::table('grading_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('grading_configs', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            if (! Schema::hasColumn('grading_configs', 'is_active')) {
                $table->boolean('is_active')->default(true)->index()->after('description');
            }
        });
    }

    public function down(): void
    {
        // Best-effort: avoid dropping columns on rollback to prevent data loss.
    }
};



