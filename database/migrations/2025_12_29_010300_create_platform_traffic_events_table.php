<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_traffic_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->string('host', 255)->nullable()->index();
            $table->string('subdomain', 80)->nullable()->index();
            $table->string('path', 500)->nullable()->index();
            $table->string('referrer', 500)->nullable();
            $table->string('ip_hash', 100)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_traffic_events');
    }
};


