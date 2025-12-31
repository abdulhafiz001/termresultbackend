<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            if (! Schema::hasColumn('announcements', 'for_teachers')) {
                $table->boolean('for_teachers')->default(false)->after('body');
            }
            if (! Schema::hasColumn('announcements', 'for_all_students')) {
                $table->boolean('for_all_students')->default(false)->after('for_teachers');
            }
            if (! Schema::hasColumn('announcements', 'class_ids')) {
                $table->json('class_ids')->nullable()->after('for_all_students');
            }
            if (! Schema::hasColumn('announcements', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('class_ids');
            }
        });
    }

    public function down(): void
    {
        // Best-effort: no destructive rollback.
    }
};



