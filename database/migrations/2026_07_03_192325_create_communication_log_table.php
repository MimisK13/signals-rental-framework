<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_log', function (Blueprint $table): void {
            $table->id();
            $table->string('notification_type_key');
            $table->string('channel');
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('recipient_address')->nullable();
            $table->string('subject_line')->nullable();
            $table->text('rendered_body')->nullable();
            $table->jsonb('attachments')->nullable();
            $table->string('status');
            $table->text('failure_reason')->nullable();
            $table->string('message_id')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('queued_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['notifiable_type', 'notifiable_id']);
            $table->index('status');
            $table->index('notification_type_key');
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_log');
    }
};
