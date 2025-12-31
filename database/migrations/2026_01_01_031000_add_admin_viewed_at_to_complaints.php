<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            if (! Schema::hasColumn('complaints', 'admin_viewed_at')) {
                $table->timestamp('admin_viewed_at')->nullable()->after('admin_response');
            }
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            if (Schema::hasColumn('complaints', 'admin_viewed_at')) {
                $table->dropColumn('admin_viewed_at');
            }
        });
    }
};


