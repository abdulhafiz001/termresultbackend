<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            if (! Schema::hasColumn('exams', 'answer_slip_released_at')) {
                $table->timestamp('answer_slip_released_at')->nullable()->after('ended_at');
            }
            if (! Schema::hasColumn('exams', 'answer_slip_released_by')) {
                $table->foreignId('answer_slip_released_by')->nullable()->after('answer_slip_released_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('exams', 'answer_slip_notes')) {
                $table->string('answer_slip_notes')->nullable()->after('answer_slip_released_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            if (Schema::hasColumn('exams', 'answer_slip_released_by')) {
                $table->dropConstrainedForeignId('answer_slip_released_by');
            }
            if (Schema::hasColumn('exams', 'answer_slip_released_at')) {
                $table->dropColumn('answer_slip_released_at');
            }
            if (Schema::hasColumn('exams', 'answer_slip_notes')) {
                $table->dropColumn('answer_slip_notes');
            }
        });
    }
};


