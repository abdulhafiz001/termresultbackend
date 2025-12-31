<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Exam question submissions: controllers write/read marks_per_question on submissions.
        Schema::table('exam_question_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('exam_question_submissions', 'marks_per_question')) {
                $table->unsignedSmallInteger('marks_per_question')->nullable()->after('question_count');
            }
        });

        // Announcement views: teacher/student controllers use viewed_at.
        Schema::table('announcement_views', function (Blueprint $table) {
            if (! Schema::hasColumn('announcement_views', 'viewed_at')) {
                $table->timestamp('viewed_at')->nullable()->useCurrent();
            }
        });
    }

    public function down(): void
    {
        // Best-effort: avoid destructive rollback.
    }
};



