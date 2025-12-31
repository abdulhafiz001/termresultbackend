<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('timetables')) {
            return;
        }

        // Add missing columns (match legacy tenant schema).
        Schema::table('timetables', function (Blueprint $table) {
            if (! Schema::hasColumn('timetables', 'venue')) {
                $table->string('venue')->nullable()->after('end_time');
            }
            if (! Schema::hasColumn('timetables', 'is_combined')) {
                $table->boolean('is_combined')->default(false)->after('venue');
            }
            if (! Schema::hasColumn('timetables', 'combined_class_ids')) {
                $table->json('combined_class_ids')->nullable()->after('is_combined');
            }
            if (! Schema::hasColumn('timetables', 'notes')) {
                $table->text('notes')->nullable()->after('combined_class_ids');
            }
        });

        // Fix wrong column type: legacy schema uses string day name (Monday..Friday).
        // Laravel "change()" would require doctrine/dbal, so we do a safe raw alter for mysql/mariadb.
        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true) && Schema::hasColumn('timetables', 'day_of_week')) {
            DB::statement('ALTER TABLE `timetables` MODIFY `day_of_week` VARCHAR(20) NOT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('timetables')) {
            return;
        }

        Schema::table('timetables', function (Blueprint $table) {
            // Best-effort rollback (type rollback skipped).
            if (Schema::hasColumn('timetables', 'notes')) {
                $table->dropColumn('notes');
            }
            if (Schema::hasColumn('timetables', 'combined_class_ids')) {
                $table->dropColumn('combined_class_ids');
            }
            if (Schema::hasColumn('timetables', 'is_combined')) {
                $table->dropColumn('is_combined');
            }
            if (Schema::hasColumn('timetables', 'venue')) {
                $table->dropColumn('venue');
            }
        });
    }
};



