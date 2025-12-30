<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subdomain')->unique();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('address')->nullable();

            $table->enum('status', ['pending', 'active', 'declined'])->default('pending');
            $table->string('database_name')->nullable()->unique();
            $table->text('decline_reason')->nullable();

            $table->json('theme')->nullable(); // { primary, secondary, logo_url, ... }
            $table->json('feature_toggles')->nullable(); // { fees: true, complaints: true, ... }

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};


