<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('study_materials', function (Blueprint $table) {
            if (! Schema::hasColumn('study_materials', 'class_id')) {
                $table->foreignId('class_id')->nullable()->after('uploaded_by')
                    ->constrained('classes')->nullOnDelete();
            }

            if (! Schema::hasColumn('study_materials', 'description')) {
                $table->text('description')->nullable()->after('title');
            }

            if (! Schema::hasColumn('study_materials', 'file_original_name')) {
                $table->string('file_original_name')->nullable()->after('file_path');
            }

            if (! Schema::hasColumn('study_materials', 'file_mime')) {
                $table->string('file_mime', 120)->nullable()->after('file_original_name');
            }

            if (! Schema::hasColumn('study_materials', 'file_size')) {
                $table->unsignedBigInteger('file_size')->nullable()->after('file_mime');
            }

            $table->index(['tenant_id', 'class_id', 'subject_id'], 'idx_materials_tenant_class_subject');
        });
    }

    public function down(): void
    {
        Schema::table('study_materials', function (Blueprint $table) {
            if (Schema::hasColumn('study_materials', 'file_size')) {
                $table->dropColumn('file_size');
            }
            if (Schema::hasColumn('study_materials', 'file_mime')) {
                $table->dropColumn('file_mime');
            }
            if (Schema::hasColumn('study_materials', 'file_original_name')) {
                $table->dropColumn('file_original_name');
            }
            if (Schema::hasColumn('study_materials', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('study_materials', 'class_id')) {
                $table->dropConstrainedForeignId('class_id');
            }

            // Index drop (ignore if missing)
            try {
                $table->dropIndex('idx_materials_tenant_class_subject');
            } catch (\Throwable $e) {
                // no-op
            }
        });
    }
};


