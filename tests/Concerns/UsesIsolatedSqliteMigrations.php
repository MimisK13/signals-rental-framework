<?php

namespace Tests\Concerns;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Runs schema rollback/re-apply migration tests on a disposable SQLite file DB
 * instead of the parallel worker's shared :memory: connection.
 *
 * Migration tests call Artisan migrate:rollback/migrate, which commits DDL outside
 * RefreshDatabase's transaction wrapper and can poison the worker DB for every
 * subsequent test (DatabaseMigrations runs migrate:fresh on the same PDO).
 */
trait UsesIsolatedSqliteMigrations
{
    private static ?string $sharedIsolatedSqlitePath = null;

    private static bool $shutdownCleanupRegistered = false;

    protected function setUpUsesIsolatedSqliteMigrations(): void
    {
        RefreshDatabaseState::$migrated = true;

        if (self::$sharedIsolatedSqlitePath === null) {
            self::$sharedIsolatedSqlitePath = tempnam(sys_get_temp_dir(), 'signals_migrations_').'.sqlite';
        }

        if (! self::$shutdownCleanupRegistered) {
            self::$shutdownCleanupRegistered = true;
            register_shutdown_function([self::class, 'cleanupSharedIsolatedSqliteMigrationFile']);
        }

        config([
            'database.default' => 'sqlite_isolated_migrations',
            'database.connections.sqlite_isolated_migrations' => [
                'driver' => 'sqlite',
                'url' => null,
                'database' => self::$sharedIsolatedSqlitePath,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        DB::purge('sqlite_isolated_migrations');
        DB::setDefaultConnection('sqlite_isolated_migrations');

        $this->endOpenDatabaseTransactions(['sqlite', 'sqlite_isolated_migrations']);

        Artisan::call('migrate:fresh', [
            '--force' => true,
            '--database' => 'sqlite_isolated_migrations',
        ]);

        $this->app[Kernel::class]->setArtisan(null);
    }

    /**
     * @param  list<string>  $connections
     */
    private function endOpenDatabaseTransactions(array $connections): void
    {
        $database = $this->app->make('db');

        foreach ($connections as $name) {
            try {
                $connection = $database->connection($name);
            } catch (\Throwable) {
                continue;
            }

            while ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
        }
    }

    protected function tearDownUsesIsolatedSqliteMigrations(): void
    {
        DB::purge('sqlite_isolated_migrations');
        DB::setDefaultConnection('sqlite');

        config(['database.default' => 'sqlite']);
    }

    public static function cleanupSharedIsolatedSqliteMigrationFile(): void
    {
        if (self::$sharedIsolatedSqlitePath !== null && is_file(self::$sharedIsolatedSqlitePath)) {
            @unlink(self::$sharedIsolatedSqlitePath);
        }

        self::$sharedIsolatedSqlitePath = null;
    }
}
