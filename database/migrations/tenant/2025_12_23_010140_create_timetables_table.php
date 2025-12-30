<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('day_of_week'); // Monday, Tuesday, Wednesday, Thursday, Friday
            $table->time('start_time');
            $table->time('end_time');
            $table->string('venue')->nullable(); // e.g., "Room 101", "Lab A"
            $table->boolean('is_combined')->default(false); // For combined classes
            $table->json('combined_class_ids')->nullable(); // Array of class IDs if combined
            $table->text('notes')->nullable();
            $table->timestamps();

            // Prevent same teacher teaching different classes at same time (unless combined)
            $table->unique(['teacher_id', 'day_of_week', 'start_time', 'end_time'], 'teacher_time_unique');
            
            // Prevent same class having multiple subjects at same time
            $table->unique(['class_id', 'day_of_week', 'start_time', 'end_time'], 'class_time_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetables');
    }
};

