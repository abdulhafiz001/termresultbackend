<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_views', function (Blueprint $table) {
            $table->id();
            // NOTE: This migration originally ran before `announcements` existed in some tenant setups,
            // causing FK creation to fail during provisioning.
            // We create the columns without FK constraints here and add constraints in a later migration.
            $table->unsignedBigInteger('announcement_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('viewed_at')->useCurrent();
            $table->unique(['announcement_id', 'user_id']);
            $table->index(['announcement_id']);
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_views');
    }
};

