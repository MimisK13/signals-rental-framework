<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_type_id')->unique()->constrained()->cascadeOnDelete();
            $table->jsonb('channels')->nullable();
            $table->jsonb('channel_config')->nullable();
            $table->jsonb('recipient_rules')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};
