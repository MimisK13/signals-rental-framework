<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('channel');
            $table->string('driver');
            $table->string('name');
            $table->jsonb('config')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('last_test_status', 20)->nullable();
            $table->text('last_test_message')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();

            $table->index(['channel', 'is_active']);
            $table->index('driver');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_providers');
    }
};
