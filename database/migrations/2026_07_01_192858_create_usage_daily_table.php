<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_daily', function (Blueprint $table) {
            $table->id();
            $table->string('dedupe_key')->unique();
            $table->date('date');
            $table->string('channel');
            $table->foreignId('personal_access_token_id')->nullable()->constrained('personal_access_tokens')->nullOnDelete();
            $table->foreignId('inbound_webhook_source_id')->nullable()->constrained('inbound_webhook_sources')->nullOnDelete();
            $table->string('key');
            $table->string('resource')->nullable();
            $table->string('status_class');
            $table->unsignedBigInteger('request_count')->default(0);
            $table->unsignedBigInteger('weighted_units')->default(0);
            $table->timestamps();

            $table->index('date');
            $table->index(['date', 'channel']);
            $table->index('personal_access_token_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_daily');
    }
};
