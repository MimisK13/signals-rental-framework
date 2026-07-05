<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inbound_webhook_source_id')->constrained('inbound_webhook_sources')->cascadeOnDelete();
            $table->string('event')->nullable();
            $table->jsonb('headers')->nullable();
            $table->jsonb('payload')->nullable();
            $table->boolean('signature_valid');
            $table->integer('status_code');
            $table->uuid('request_id');
            $table->integer('price_weight')->default(0);
            $table->integer('weighted_units')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->index(['inbound_webhook_source_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_webhook_logs');
    }
};
