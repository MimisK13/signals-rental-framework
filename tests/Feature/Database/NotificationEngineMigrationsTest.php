<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * @return list<string>
 */
function notificationEngineMigrationRollbackPaths(): array
{
    return [
        'database/migrations/2026_07_03_101911_add_notification_engine_foundation_columns.php',
    ];
}

describe('notification engine migrations (sqlite)', function () {
    it('adds notification engine foundation columns', function () {
        expect(Schema::hasColumn('notification_types', 'audience'))->toBeTrue()
            ->and(Schema::hasColumn('notification_types', 'default_recipients'))->toBeTrue()
            ->and(Schema::hasColumn('notification_types', 'data_schema'))->toBeTrue()
            ->and(Schema::hasColumn('notification_settings', 'channel_config'))->toBeTrue()
            ->and(Schema::hasColumn('notification_settings', 'recipient_rules'))->toBeTrue()
            ->and(Schema::hasColumn('members', 'communication_preferences'))->toBeTrue();
    });

    it('rolls back and re-applies notification engine foundation migrations cleanly', function () {
        foreach (notificationEngineMigrationRollbackPaths() as $path) {
            Artisan::call('migrate:rollback', [
                '--path' => $path,
                '--force' => true,
            ]);
        }

        expect(Schema::hasColumn('notification_types', 'audience'))->toBeFalse()
            ->and(Schema::hasColumn('notification_settings', 'channel_config'))->toBeFalse()
            ->and(Schema::hasColumn('members', 'communication_preferences'))->toBeFalse();

        foreach (notificationEngineMigrationRollbackPaths() as $path) {
            Artisan::call('migrate', [
                '--path' => $path,
                '--force' => true,
            ]);
        }

        expect(Schema::hasColumn('notification_types', 'data_schema'))->toBeTrue()
            ->and(Schema::hasColumn('members', 'communication_preferences'))->toBeTrue();
    });
});
