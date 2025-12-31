<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('promotion_rules')) {
            Schema::table('promotion_rules', function (Blueprint $table) {
                if (! Schema::hasColumn('promotion_rules', 'type')) {
                    $table->string('type', 80)->nullable()->after('name');
                }
                if (! Schema::hasColumn('promotion_rules', 'description')) {
                    $table->text('description')->nullable()->after('type');
                }
                if (! Schema::hasColumn('promotion_rules', 'criteria')) {
                    $table->json('criteria')->nullable()->after('description');
                }
                if (! Schema::hasColumn('promotion_rules', 'is_active')) {
                    $table->boolean('is_active')->default(false)->index()->after('criteria');
                }
            });
        }

        // Controller writes a JSON "summary" per promotion run.
        if (Schema::hasTable('student_promotions')) {
            Schema::table('student_promotions', function (Blueprint $table) {
                if (! Schema::hasColumn('student_promotions', 'summary')) {
                    $table->json('summary')->nullable()->after('status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('student_promotions') && Schema::hasColumn('student_promotions', 'summary')) {
            Schema::table('student_promotions', function (Blueprint $table) {
                $table->dropColumn('summary');
            });
        }

        if (Schema::hasTable('promotion_rules')) {
            Schema::table('promotion_rules', function (Blueprint $table) {
                if (Schema::hasColumn('promotion_rules', 'is_active')) {
                    $table->dropColumn('is_active');
                }
                if (Schema::hasColumn('promotion_rules', 'criteria')) {
                    $table->dropColumn('criteria');
                }
                if (Schema::hasColumn('promotion_rules', 'description')) {
                    $table->dropColumn('description');
                }
                if (Schema::hasColumn('promotion_rules', 'type')) {
                    $table->dropColumn('type');
                }
            });
        }
    }
};


