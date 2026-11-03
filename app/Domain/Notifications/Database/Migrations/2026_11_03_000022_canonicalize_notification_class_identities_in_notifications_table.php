<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private const IDENTITIES = [
        'App\\Notifications\\ChannelInfrastructureBroadcastNotification' => 'App\\Domain\\Notifications\\Notifications\\ChannelInfrastructureBroadcastNotification',
        'App\\Notifications\\ChannelInfrastructureDatabaseNotification' => 'App\\Domain\\Notifications\\Notifications\\ChannelInfrastructureDatabaseNotification',
        'App\\Notifications\\DomainEventNotification' => 'App\\Domain\\Notifications\\Notifications\\DomainEventNotification',
        'App\\Notifications\\FailingTestPipelineNotification' => 'App\\Domain\\Notifications\\Notifications\\FailingTestPipelineNotification',
        'App\\Notifications\\MagicLinkLoginNotification' => 'App\\Domain\\Notifications\\Notifications\\MagicLinkLoginNotification',
        'App\\Notifications\\PasswordResetNotification' => 'App\\Domain\\Notifications\\Notifications\\PasswordResetNotification',
        'App\\Notifications\\TestPipelineNotification' => 'App\\Domain\\Notifications\\Notifications\\TestPipelineNotification',
        'App\\Notifications\\UserInvitedNotification' => 'App\\Domain\\Notifications\\Notifications\\UserInvitedNotification',
    ];

    public function up(): void
    {
        foreach (self::IDENTITIES as $from => $to) {
            DB::table('notifications')->where('type', $from)->update(['type' => $to]);
        }
    }

    public function down(): void
    {
        foreach (self::IDENTITIES as $from => $to) {
            DB::table('notifications')->where('type', $to)->update(['type' => $from]);
        }
    }
};
