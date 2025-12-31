<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('promotion_rules')) {
            return;
        }

        // Single-db schema originally included from_class_id/to_class_id/min_average_score.
        // Current PromotionRulesController uses type/criteria/is_active and does NOT provide from_class_id.
        // So we must allow from_class_id to be nullable.

        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true) && Schema::hasColumn('promotion_rules', 'from_class_id')) {
            // Drop FK if it exists (name may vary), then alter column to nullable, then re-add FK.
            try {
                Schema::table('promotion_rules', function (Blueprint $table) {
                    $table->dropForeign(['from_class_id']);
                });
            } catch (\Throwable $e) {
                // ignore if FK doesn't exist or has a non-standard name
            }

            DB::statement('ALTER TABLE `promotion_rules` MODIFY `from_class_id` BIGINT UNSIGNED NULL');

            // Re-add FK (best-effort).
            try {
                Schema::table('promotion_rules', function (Blueprint $table) {
                    $table->foreign('from_class_id')->references('id')->on('classes')->nullOnDelete();
                });
            } catch (\Throwable $e) {
                // ignore if FK cannot be added (already exists, etc.)
            }
        }
    }

    public function down(): void
    {
        // Best-effort rollback to NOT NULL on mysql/mariadb (may fail if NULL rows exist).
        if (! Schema::hasTable('promotion_rules')) {
            return;
        }

        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true) && Schema::hasColumn('promotion_rules', 'from_class_id')) {
            try {
                Schema::table('promotion_rules', function (Blueprint $table) {
                    $table->dropForeign(['from_class_id']);
                });
            } catch (\Throwable $e) {
                // ignore
            }

            DB::statement('ALTER TABLE `promotion_rules` MODIFY `from_class_id` BIGINT UNSIGNED NOT NULL');

            try {
                Schema::table('promotion_rules', function (Blueprint $table) {
                    $table->foreign('from_class_id')->references('id')->on('classes')->cascadeOnDelete();
                });
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }
};


