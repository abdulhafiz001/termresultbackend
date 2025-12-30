<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_question_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('exam_question_submissions', 'marks_per_question')) {
                $table->unsignedSmallInteger('marks_per_question')->nullable()->after('question_count');
            }
        });

        Schema::table('exams', function (Blueprint $table) {
            if (! Schema::hasColumn('exams', 'marks_per_question')) {
                $table->unsignedSmallInteger('marks_per_question')->nullable()->after('question_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('exam_question_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('exam_question_submissions', 'marks_per_question')) {
                $table->dropColumn('marks_per_question');
            }
        });

        Schema::table('exams', function (Blueprint $table) {
            if (Schema::hasColumn('exams', 'marks_per_question')) {
                $table->dropColumn('marks_per_question');
            }
        });
    }
};


