<?php

use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesIsolatedSqliteMigrations;

uses(UsesIsolatedSqliteMigrations::class);

describe('notification engine migrations (sqlite)', function () {
    it('adds notification engine foundation columns', function () {
        expect(Schema::hasColumn('notification_types', 'audience'))->toBeTrue()
            ->and(Schema::hasColumn('notification_types', 'default_recipients'))->toBeTrue()
            ->and(Schema::hasColumn('notification_types', 'data_schema'))->toBeTrue()
            ->and(Schema::hasColumn('notification_settings', 'channel_config'))->toBeTrue()
            ->and(Schema::hasColumn('notification_settings', 'recipient_rules'))->toBeTrue()
            ->and(Schema::hasColumn('members', 'communication_preferences'))->toBeTrue();
    });

    it('keeps notification foundation columns in their consolidated table migrations', function () {
        expect(Schema::hasColumn('notification_types', 'data_schema'))->toBeTrue()
            ->and(Schema::hasColumn('notification_settings', 'channel_config'))->toBeTrue()
            ->and(Schema::hasColumn('notification_settings', 'recipient_rules'))->toBeTrue()
            ->and(Schema::hasColumn('members', 'communication_preferences'))->toBeTrue();
    });
});
