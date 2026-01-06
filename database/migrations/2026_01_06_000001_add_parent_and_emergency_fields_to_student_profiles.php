<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_profiles')) {
            return;
        }

        Schema::table('student_profiles', function (Blueprint $table) {
            // These fields are used by the Student Profile page (/tenant/student/me).
            if (! Schema::hasColumn('student_profiles', 'parent_name')) {
                $table->string('parent_name')->nullable()->after('address');
            }
            if (! Schema::hasColumn('student_profiles', 'parent_phone')) {
                $table->string('parent_phone')->nullable()->after('parent_name');
            }
            if (! Schema::hasColumn('student_profiles', 'parent_email')) {
                $table->string('parent_email')->nullable()->after('parent_phone');
            }
            if (! Schema::hasColumn('student_profiles', 'emergency_contact')) {
                $table->string('emergency_contact')->nullable()->after('parent_email');
            }
            if (! Schema::hasColumn('student_profiles', 'emergency_phone')) {
                $table->string('emergency_phone')->nullable()->after('emergency_contact');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('student_profiles')) {
            return;
        }

        Schema::table('student_profiles', function (Blueprint $table) {
            // Drop in reverse order; guard each one for safety.
            if (Schema::hasColumn('student_profiles', 'emergency_phone')) {
                $table->dropColumn('emergency_phone');
            }
            if (Schema::hasColumn('student_profiles', 'emergency_contact')) {
                $table->dropColumn('emergency_contact');
            }
            if (Schema::hasColumn('student_profiles', 'parent_email')) {
                $table->dropColumn('parent_email');
            }
            if (Schema::hasColumn('student_profiles', 'parent_phone')) {
                $table->dropColumn('parent_phone');
            }
            if (Schema::hasColumn('student_profiles', 'parent_name')) {
                $table->dropColumn('parent_name');
            }
        });
    }
};


