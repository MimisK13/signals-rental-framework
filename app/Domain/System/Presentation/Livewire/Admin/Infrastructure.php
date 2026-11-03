<?php

declare(strict_types=1);

namespace App\Domain\System\Presentation\Livewire\Admin;

use App\Domain\System\Services\ConnectionTesters\PostgresConnectionTester;
use App\Domain\System\Services\ConnectionTesters\RedisConnectionTester;
use App\Domain\System\Services\ConnectionTesters\S3ConnectionTester;
use App\Domain\System\Services\InfrastructureEnvironmentWriter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Infrastructure')]
final class Infrastructure extends Component
{
    // Database
    public string $dbHost = '';

    public int $dbPort = 5432;

    public string $dbDatabase = '';

    public string $dbUsername = '';

    public string $dbPassword = '';

    // Redis
    public string $redisHost = '';

    public int $redisPort = 6379;

    public string $redisPassword = '';

    // S3
    public string $s3Key = '';

    public string $s3Secret = '';

    public string $s3Region = '';

    public string $s3Bucket = '';

    public string $s3Endpoint = '';

    public bool $s3PathStyle = false;

    // Queue
    public string $queueConnection = 'database';

    // Test results
    /** @var array{success: bool, version?: string|null, error?: string|null}|null */
    public ?array $dbTestResult = null;

    /** @var array{success: bool, version?: string|null, error?: string|null}|null */
    public ?array $redisTestResult = null;

    /** @var array{success: bool, error?: string|null}|null */
    public ?array $s3TestResult = null;

    public function mount(): void
    {
        Gate::authorize('owner');

        $this->dbHost = (string) config('database.connections.pgsql.host', '127.0.0.1');
        $this->dbPort = (int) config('database.connections.pgsql.port', 5432);
        $this->dbDatabase = (string) config('database.connections.pgsql.database', 'signals');
        $this->dbUsername = (string) config('database.connections.pgsql.username', 'postgres');
        $this->redisHost = (string) config('database.redis.default.host', '127.0.0.1');
        $this->redisPort = (int) config('database.redis.default.port', 6379);

        $this->s3Region = (string) config('filesystems.disks.s3.region', 'us-east-1');
        $this->s3Bucket = (string) config('filesystems.disks.s3.bucket', '');
        $this->s3Endpoint = (string) config('filesystems.disks.s3.endpoint', '');
        $this->s3PathStyle = (bool) config('filesystems.disks.s3.use_path_style_endpoint', false);

        $this->queueConnection = (string) config('queue.default', 'database');
    }

    public function testDatabase(): void
    {
        Gate::authorize('owner');

        $this->dbTestResult = app(PostgresConnectionTester::class)->test([
            'host' => $this->dbHost,
            'port' => $this->dbPort,
            'database' => $this->dbDatabase,
            'username' => $this->dbUsername,
            'password' => $this->resolvedDatabasePassword(),
        ]);
    }

    public function testRedis(): void
    {
        Gate::authorize('owner');

        $this->redisTestResult = app(RedisConnectionTester::class)->test([
            'host' => $this->redisHost,
            'port' => $this->redisPort,
            'password' => $this->resolvedRedisPassword(),
        ]);
    }

    public function testS3(): void
    {
        Gate::authorize('owner');

        $this->s3TestResult = app(S3ConnectionTester::class)->test([
            'key' => $this->resolvedS3Key(),
            'secret' => $this->resolvedS3Secret(),
            'region' => $this->s3Region,
            'bucket' => $this->s3Bucket,
            'endpoint' => $this->s3Endpoint ?: null,
            'use_path_style' => $this->s3PathStyle,
        ]);
    }

    public function saveDatabase(): void
    {
        Gate::authorize('owner');

        $this->validate([
            'dbHost' => ['required', 'string', 'max:255'],
            'dbPort' => ['required', 'integer', 'min:1', 'max:65535'],
            'dbDatabase' => ['required', 'string', 'max:255'],
            'dbUsername' => ['required', 'string', 'max:255'],
            'dbPassword' => ['nullable', 'string', 'max:255'],
        ]);

        app(InfrastructureEnvironmentWriter::class)->write([
            'DB_HOST' => $this->dbHost,
            'DB_PORT' => (string) $this->dbPort,
            'DB_DATABASE' => $this->dbDatabase,
            'DB_USERNAME' => $this->dbUsername,
            'DB_PASSWORD' => $this->resolvedDatabasePassword(),
        ]);

        $this->dispatch('infrastructure-saved');
    }

    public function saveRedis(): void
    {
        Gate::authorize('owner');

        $this->validate([
            'redisHost' => ['required', 'string', 'max:255'],
            'redisPort' => ['required', 'integer', 'min:1', 'max:65535'],
            'redisPassword' => ['nullable', 'string', 'max:255'],
        ]);

        app(InfrastructureEnvironmentWriter::class)->write([
            'REDIS_HOST' => $this->redisHost,
            'REDIS_PORT' => (string) $this->redisPort,
            'REDIS_PASSWORD' => $this->resolvedRedisPassword() ?? 'null',
        ]);

        $this->dispatch('infrastructure-saved');
    }

    public function saveS3(): void
    {
        Gate::authorize('owner');

        $keyRules = $this->resolvedS3Key() === ''
            ? ['required', 'string', 'max:255']
            : ['nullable', 'string', 'max:255'];
        $secretRules = $this->resolvedS3Secret() === ''
            ? ['required', 'string', 'max:255']
            : ['nullable', 'string', 'max:255'];

        $this->validate([
            's3Key' => $keyRules,
            's3Secret' => $secretRules,
            's3Region' => ['required', 'string', 'max:50'],
            's3Bucket' => ['required', 'string', 'max:255'],
            's3Endpoint' => ['nullable', 'string', 'max:500'],
        ]);

        $resolvedKey = $this->resolvedS3Key();
        $resolvedSecret = $this->resolvedS3Secret();

        app(InfrastructureEnvironmentWriter::class)->write([
            'AWS_ACCESS_KEY_ID' => $resolvedKey,
            'AWS_SECRET_ACCESS_KEY' => $resolvedSecret,
            'AWS_DEFAULT_REGION' => $this->s3Region,
            'AWS_BUCKET' => $this->s3Bucket,
            'AWS_ENDPOINT' => $this->s3Endpoint ?: '',
            'AWS_USE_PATH_STYLE_ENDPOINT' => $this->s3PathStyle ? 'true' : 'false',
        ]);

        $this->dispatch('infrastructure-saved');
    }

    public function saveQueue(): void
    {
        Gate::authorize('owner');

        $this->validate([
            'queueConnection' => ['required', 'string', 'in:database,redis'],
        ]);

        app(InfrastructureEnvironmentWriter::class)->write([
            'QUEUE_CONNECTION' => $this->queueConnection,
        ]);

        $this->dispatch('infrastructure-saved');
    }

    private function resolvedDatabasePassword(): string
    {
        return $this->dbPassword !== ''
            ? $this->dbPassword
            : (string) config('database.connections.pgsql.password', '');
    }

    private function resolvedRedisPassword(): ?string
    {
        $password = $this->redisPassword !== ''
            ? $this->redisPassword
            : (string) config('database.redis.default.password', '');

        return $password === '' || $password === 'null' ? null : $password;
    }

    private function resolvedS3Key(): string
    {
        return $this->s3Key !== ''
            ? $this->s3Key
            : (string) config('filesystems.disks.s3.key', '');
    }

    private function resolvedS3Secret(): string
    {
        return $this->s3Secret !== ''
            ? $this->s3Secret
            : (string) config('filesystems.disks.s3.secret', '');
    }

    public function render(): View
    {
        return view('system::admin.infrastructure');
    }
}
