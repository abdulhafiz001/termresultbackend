<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['complaint', 'suggestion'])->index();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->enum('status', ['open', 'in_review', 'resolved'])->default('open')->index();
            $table->text('admin_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};


