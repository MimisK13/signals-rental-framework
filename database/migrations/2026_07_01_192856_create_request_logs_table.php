<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel')->default('api');
            $table->uuid('request_id');
            $table->string('method');
            $table->text('uri');
            $table->string('route_name')->nullable();
            $table->string('resource')->nullable();
            $table->string('operation')->nullable();
            $table->foreignId('personal_access_token_id')->nullable()->constrained('personal_access_tokens')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ability')->nullable();
            $table->integer('status_code');
            $table->integer('duration_ms');
            $table->integer('request_bytes')->default(0);
            $table->integer('response_bytes')->default(0);
            $table->integer('price_weight')->default(1);
            $table->integer('weighted_units')->default(1);
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('request_body')->nullable();
            $table->text('response_body')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('request_id');
            $table->index('created_at');
            $table->index('personal_access_token_id');
            $table->index('route_name');
            $table->index('status_code');
            $table->index(['created_at', 'personal_access_token_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_logs');
    }
};
