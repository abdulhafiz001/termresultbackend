<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('file_deletions')) {
            Schema::create('file_deletions', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->string('file_path');
                $table->timestamp('deleted_at')->nullable()->index();
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedBigInteger('academic_session_id')->nullable()->index();
                $table->unsignedBigInteger('term_id')->nullable()->index();
                $table->string('reason', 50)->default('backup_cleanup'); // backup_cleanup | manual | other
                $table->timestamps();

                $table->unique(['tenant_id', 'file_path'], 'uniq_file_deletions_tenant_path');
                $table->index(['tenant_id', 'academic_session_id', 'term_id'], 'idx_file_deletions_scope');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('file_deletions');
    }
};


