<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('complaints')) {
            return;
        }

        Schema::table('complaints', function (Blueprint $table) {
            if (! Schema::hasColumn('complaints', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->index()->after('id');
            }
            if (! Schema::hasColumn('complaints', 'type')) {
                $table->string('type', 30)->default('complaint')->index()->after('student_id');
            }
            if (! Schema::hasColumn('complaints', 'admin_response')) {
                $table->text('admin_response')->nullable()->after('message');
            }
        });

        // Make subject nullable to match controller validation (mysql/mariadb only, no doctrine/dbal).
        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true) && Schema::hasColumn('complaints', 'subject')) {
            DB::statement('ALTER TABLE `complaints` MODIFY `subject` VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        // Best-effort rollback (avoid destructive drops of tenant_id/type/admin_response).
    }
};


