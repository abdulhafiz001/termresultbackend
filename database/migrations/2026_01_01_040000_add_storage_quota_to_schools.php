<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (! Schema::hasColumn('schools', 'storage_quota_mb')) {
                $table->unsignedInteger('storage_quota_mb')->default(200)->after('feature_toggles');
            }
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (Schema::hasColumn('schools', 'storage_quota_mb')) {
                $table->dropColumn('storage_quota_mb');
            }
        });
    }
};


