<?php

use App\Models\NotificationType;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesPostgres;

/*
| PostgreSQL lane: proves JSONB notification columns migrate cleanly on real Postgres.
|
|   php artisan test --compact tests/Pgsql/NotificationEngineMigrationsPostgresTest.php
*/

uses(UsesPostgres::class)->group('pgsql');

describe('notification engine migrations (pgsql)', function () {
    it('adds notification engine foundation columns on postgres', function () {
        expect(Schema::hasColumn('notification_types', 'audience'))->toBeTrue()
            ->and(Schema::hasColumn('notification_types', 'default_recipients'))->toBeTrue()
            ->and(Schema::hasColumn('notification_settings', 'recipient_rules'))->toBeTrue()
            ->and(Schema::hasColumn('members', 'communication_preferences'))->toBeTrue();
    });

    it('rolls back notification engine foundation migrations on postgres', function () {
        $path = 'database/migrations/2026_07_03_101911_add_notification_engine_foundation_columns.php';

        $this->artisan('migrate:rollback', [
            '--path' => $path,
            '--database' => 'pgsql_testing',
            '--force' => true,
        ])->assertSuccessful();

        expect(Schema::connection('pgsql_testing')->hasColumn('notification_types', 'audience'))->toBeFalse()
            ->and(Schema::connection('pgsql_testing')->hasColumn('members', 'communication_preferences'))->toBeFalse();

        $this->artisan('migrate', [
            '--path' => $path,
            '--database' => 'pgsql_testing',
            '--force' => true,
        ])->assertSuccessful();

        expect(Schema::connection('pgsql_testing')->hasColumn('notification_types', 'data_schema'))->toBeTrue();
    });

    it('persists jsonb notification metadata on postgres', function () {
        $type = NotificationType::factory()->create([
            'default_recipients' => [['type' => 'contact']],
            'data_schema' => ['groups' => []],
        ]);

        $type->refresh();

        expect($type->default_recipients)->toBe([['type' => 'contact']])
            ->and($type->data_schema)->toBe(['groups' => []]);
    });
});
