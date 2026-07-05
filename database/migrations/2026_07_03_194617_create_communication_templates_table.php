<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_type_id')->nullable()->constrained('notification_types')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('channel');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->jsonb('attachments_config')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('last_edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'notification_type_id', 'channel', 'is_active']);
            $table->unique(['store_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_templates');
    }
};
