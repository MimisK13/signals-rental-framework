<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_types', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('category');
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->string('audience')->default('internal');
            $table->jsonb('available_channels');
            $table->jsonb('default_channels');
            $table->jsonb('default_recipients')->nullable();
            $table->jsonb('data_schema')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('source', 50)->default('core');
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('notification_types');
    }
};
