<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();

            $table->unsignedTinyInteger('ca1')->nullable();
            $table->unsignedTinyInteger('ca2')->nullable();
            $table->unsignedTinyInteger('exam')->nullable();
            $table->unsignedTinyInteger('total')->nullable();
            $table->string('grade')->nullable();
            $table->string('remark')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete(); // teacher
            $table->timestamps();

            $table->unique(['student_id', 'subject_id', 'academic_session_id', 'term_id'], 'uniq_student_subject_term');
            $table->index(['academic_session_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_scores');
    }
};


