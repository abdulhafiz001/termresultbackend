<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('announcement_views')) return;
        if (!Schema::hasTable('announcements')) return;
        if (!Schema::hasTable('users')) return;

        Schema::table('announcement_views', function (Blueprint $table) {
            // Ensure columns exist (older tenants / partial migrations).
            if (!Schema::hasColumn('announcement_views', 'announcement_id')) {
                $table->unsignedBigInteger('announcement_id')->nullable();
            }
            if (!Schema::hasColumn('announcement_views', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable();
            }
        });

        // Add FKs best-effort (avoid failing provisioning if FKs already exist).
        try {
            DB::statement('ALTER TABLE `announcement_views` ADD CONSTRAINT `announcement_views_announcement_id_foreign` FOREIGN KEY (`announcement_id`) REFERENCES `announcements`(`id`) ON DELETE CASCADE');
        } catch (\Throwable $e) {
            // ignore (already exists / different constraint)
        }
        try {
            DB::statement('ALTER TABLE `announcement_views` ADD CONSTRAINT `announcement_views_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE');
        } catch (\Throwable $e) {
            // ignore (already exists / different constraint)
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('announcement_views')) return;

        // Best-effort drop (constraint may not exist in some tenants).
        try { DB::statement('ALTER TABLE `announcement_views` DROP FOREIGN KEY `announcement_views_announcement_id_foreign`'); } catch (\Throwable $e) {}
        try { DB::statement('ALTER TABLE `announcement_views` DROP FOREIGN KEY `announcement_views_user_id_foreign`'); } catch (\Throwable $e) {}
    }
};


