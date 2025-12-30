<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'restrictions')) {
                $table->json('restrictions')->nullable()->after('status');
            }
            if (! Schema::hasColumn('users', 'restriction_reason')) {
                $table->text('restriction_reason')->nullable()->after('restrictions');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'restriction_reason')) {
                $table->dropColumn('restriction_reason');
            }
            if (Schema::hasColumn('users', 'restrictions')) {
                $table->dropColumn('restrictions');
            }
        });
    }
};


