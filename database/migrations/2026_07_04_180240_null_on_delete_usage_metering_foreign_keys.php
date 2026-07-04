<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_daily', function (Blueprint $table): void {
            $table->dropForeign(['personal_access_token_id']);
            $table->dropForeign(['inbound_webhook_source_id']);
        });

        Schema::table('usage_daily', function (Blueprint $table): void {
            $table->foreign('personal_access_token_id')
                ->references('id')
                ->on('personal_access_tokens')
                ->nullOnDelete();

            $table->foreign('inbound_webhook_source_id')
                ->references('id')
                ->on('inbound_webhook_sources')
                ->nullOnDelete();
        });

        Schema::table('request_logs', function (Blueprint $table): void {
            $table->dropForeign(['personal_access_token_id']);
        });

        Schema::table('request_logs', function (Blueprint $table): void {
            $table->foreign('personal_access_token_id')
                ->references('id')
                ->on('personal_access_tokens')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('usage_daily', function (Blueprint $table): void {
            $table->dropForeign(['personal_access_token_id']);
            $table->dropForeign(['inbound_webhook_source_id']);
        });

        Schema::table('usage_daily', function (Blueprint $table): void {
            $table->foreign('personal_access_token_id')
                ->references('id')
                ->on('personal_access_tokens')
                ->nullOnDelete();

            $table->foreign('inbound_webhook_source_id')
                ->references('id')
                ->on('inbound_webhook_sources')
                ->nullOnDelete();
        });

        Schema::table('request_logs', function (Blueprint $table): void {
            $table->dropForeign(['personal_access_token_id']);
        });

        Schema::table('request_logs', function (Blueprint $table): void {
            $table->foreign('personal_access_token_id')
                ->references('id')
                ->on('personal_access_tokens')
                ->nullOnDelete();
        });
    }
};
