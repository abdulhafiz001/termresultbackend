<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            if (! Schema::hasColumn('exam_attempts', 'continue_key_plain')) {
                $table->string('continue_key_plain', 64)->nullable()->after('continue_token_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            if (Schema::hasColumn('exam_attempts', 'continue_key_plain')) {
                $table->dropColumn('continue_key_plain');
            }
        });
    }
};

