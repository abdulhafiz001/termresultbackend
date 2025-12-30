<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('grading_config_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_config_id')->constrained('grading_configs')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['grading_config_id', 'class_id'], 'uniq_grading_cfg_class');
            $table->index(['class_id', 'grading_config_id'], 'idx_class_grading_cfg');
        });

        Schema::create('grading_config_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_config_id')->constrained('grading_configs')->cascadeOnDelete();
            $table->string('grade', 2); // A, B, C, D, E, F
            $table->unsignedTinyInteger('min_score'); // inclusive
            $table->unsignedTinyInteger('max_score'); // inclusive
            $table->timestamps();
            $table->unique(['grading_config_id', 'grade'], 'uniq_grading_cfg_grade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_config_ranges');
        Schema::dropIfExists('grading_config_classes');
        Schema::dropIfExists('grading_configs');
    }
};


