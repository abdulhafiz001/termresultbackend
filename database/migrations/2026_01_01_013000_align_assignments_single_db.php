<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Align central single-db assignments tables with what the existing controllers expect.
        // (Controllers were originally written against database/migrations/tenant assignments schema.)

        Schema::table('assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('assignments', 'question')) {
                $table->text('question')->nullable()->after('term_id');
            }
            if (! Schema::hasColumn('assignments', 'image_path')) {
                $table->string('image_path')->nullable()->after('question');
            }
        });

        Schema::table('assignment_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('assignment_submissions', 'answer')) {
                $table->text('answer')->nullable()->after('student_id');
            }
            if (! Schema::hasColumn('assignment_submissions', 'score')) {
                $table->decimal('score', 5, 2)->nullable()->after('answer');
            }
            if (! Schema::hasColumn('assignment_submissions', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('score');
            }
        });
    }

    public function down(): void
    {
        // Best-effort: avoid destructive rollback.
    }
};



