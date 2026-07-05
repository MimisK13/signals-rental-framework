<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_types', function (Blueprint $table): void {
            $table->string('audience')->default('internal')->after('description');
            $table->jsonb('default_recipients')->nullable()->after('default_channels');
            $table->jsonb('data_schema')->nullable()->after('default_recipients');
        });

        Schema::table('notification_settings', function (Blueprint $table): void {
            $table->jsonb('channel_config')->nullable()->after('channels');
            $table->jsonb('recipient_rules')->nullable()->after('channel_config');
        });

        Schema::table('members', function (Blueprint $table): void {
            $table->jsonb('communication_preferences')->nullable()->after('department');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->dropColumn('communication_preferences');
        });

        Schema::table('notification_settings', function (Blueprint $table): void {
            $table->dropColumn(['channel_config', 'recipient_rules']);
        });

        Schema::table('notification_types', function (Blueprint $table): void {
            $table->dropColumn(['audience', 'default_recipients', 'data_schema']);
        });
    }
};
