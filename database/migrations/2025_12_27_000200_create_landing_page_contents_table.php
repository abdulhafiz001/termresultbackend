<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_page_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();

            $table->string('hero_title')->nullable();
            $table->string('hero_subtitle')->nullable();
            $table->text('hero_description')->nullable();

            // Stored as JSON array: [{text, author, role}]
            $table->json('testimonials')->nullable();

            $table->string('school_email')->nullable();
            $table->string('school_phone')->nullable();
            $table->string('school_address')->nullable();

            $table->timestamps();

            $table->unique('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_page_contents');
    }
};


