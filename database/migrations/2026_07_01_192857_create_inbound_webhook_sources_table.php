<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_webhook_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('secret');
            $table->string('signature_algo')->default('sha256');
            $table->string('signature_header')->default('X-Signature');
            $table->jsonb('expected_events')->nullable();
            $table->integer('price_weight')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_webhook_sources');
    }
};
