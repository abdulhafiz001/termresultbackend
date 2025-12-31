<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'term_id')) {
                $table->foreignId('term_id')->nullable()->after('academic_session_id')
                    ->constrained('terms')->nullOnDelete();
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            // Helpful filter index
            $table->index(['tenant_id', 'academic_session_id', 'term_id'], 'idx_payments_tenant_session_term');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            try {
                $table->dropIndex('idx_payments_tenant_session_term');
            } catch (\Throwable $e) {
                // ignore
            }
            if (Schema::hasColumn('payments', 'term_id')) {
                $table->dropConstrainedForeignId('term_id');
            }
        });
    }
};


