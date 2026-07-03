<?php

use App\Contracts\Notifications\ChannelDriver;
use App\Data\Notifications\ChannelConfigFieldData;
use App\Data\Notifications\ChannelSendData;
use App\Data\Notifications\ChannelTestResultData;
use App\Data\Notifications\TestChannelProviderData;
use App\Jobs\SendChannelNotification;
use App\Mail\TemplatedEmail;
use App\Models\Member;
use App\Services\Notifications\ChannelConfigMasker;
use App\Services\Notifications\ChannelProviderRegistry;
use App\Services\Notifications\Drivers\BroadcastDriver;
use App\Services\Notifications\Drivers\DatabaseDriver;
use App\Services\Notifications\Drivers\EmailDriver;
use App\Services\Plugins\PluginRegistrar;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

class FakePluginChannelDriver implements ChannelDriver
{
    public static bool $sendCalled = false;

    /** @var array<string, mixed> */
    public static array $lastConfig = [];

    public static ?ChannelSendData $lastPayload = null;

    public function key(): string
    {
        return 'fake_plugin';
    }

    public function channel(): string
    {
        return 'fake_channel';
    }

    public function label(): string
    {
        return 'Fake Plugin Channel';
    }

    /**
     * @return list<ChannelConfigFieldData>
     */
    public function configSchema(): array
    {
        return [
            new ChannelConfigFieldData('api_key', 'API Key', 'password', required: true, secret: true),
            new ChannelConfigFieldData('webhook_url', 'Webhook URL', 'url', required: true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveConfig(): array
    {
        return [
            'api_key' => 'super-secret-token',
            'webhook_url' => 'https://example.com/hook',
        ];
    }

    public function send(ChannelSendData $payload): void
    {
        self::$sendCalled = true;
        self::$lastPayload = $payload;
        self::$lastConfig = $payload->config;
    }

    public function test(TestChannelProviderData $data): ChannelTestResultData
    {
        if (($data->config['api_key'] ?? '') === '') {
            return ChannelTestResultData::failed($this, 'API key is required.');
        }

        return ChannelTestResultData::passed($this, 'Fake plugin channel test succeeded.');
    }
}

describe('ChannelProviderRegistry core drivers', function () {
    it('registers the three core drivers on boot', function () {
        $registry = app(ChannelProviderRegistry::class);

        expect($registry->keys())->toContain('email', 'database', 'broadcast')
            ->and($registry->channels())->toContain('mail', 'database', 'broadcast')
            ->and($registry->has('email'))->toBeTrue()
            ->and($registry->has('database'))->toBeTrue()
            ->and($registry->has('broadcast'))->toBeTrue();
    });

    it('resolves drivers by key and channel', function () {
        $registry = app(ChannelProviderRegistry::class);

        expect($registry->resolve(driverKey: 'email'))->toBeInstanceOf(EmailDriver::class)
            ->and($registry->resolve(channel: 'mail'))->toBeInstanceOf(EmailDriver::class)
            ->and($registry->resolve(channel: 'database'))->toBeInstanceOf(DatabaseDriver::class)
            ->and($registry->resolve(channel: 'broadcast'))->toBeInstanceOf(BroadcastDriver::class);
    });

    it('throws for unknown driver keys and channels', function () {
        $registry = app(ChannelProviderRegistry::class);

        expect(fn () => $registry->resolve(driverKey: 'unknown'))
            ->toThrow(InvalidArgumentException::class, 'Unknown channel driver [unknown].');

        expect(fn () => $registry->resolve(channel: 'sms'))
            ->toThrow(InvalidArgumentException::class, 'No channel driver registered for channel [sms].');
    });

    it('lists drivers with config schema and masked secrets', function () {
        settings()->setMany([
            'email.mailer' => ['value' => 'smtp', 'type' => 'string'],
            'email.smtp_host' => 'smtp.example.com',
            'email.smtp_password' => ['value' => 'hunter2secret', 'type' => 'encrypted'],
            'email.from_address' => 'noreply@example.com',
        ]);

        $drivers = app(ChannelProviderRegistry::class)->drivers();

        expect($drivers)->toHaveCount(3);

        $email = collect($drivers)->firstWhere('key', 'email');

        expect($email)->not->toBeNull()
            ->and($email->channel)->toBe('mail')
            ->and($email->configSchema)->not->toBeEmpty()
            ->and($email->config['smtp_password'])->not->toBe('hunter2secret')
            ->and($email->config['smtp_password'])->toBe(ChannelConfigMasker::maskValue('hunter2secret'))
            ->and($email->config['smtp_host'])->toBe('smtp.example.com');
    });

    it('filters drivers by channel', function () {
        $drivers = app(ChannelProviderRegistry::class)->drivers(channel: 'database');

        expect($drivers)->toHaveCount(1)
            ->and($drivers[0]->key)->toBe('database');
    });
});

describe('ChannelProviderRegistry plugin seam', function () {
    it('registers a custom driver via PluginRegistrar', function () {
        FakePluginChannelDriver::$sendCalled = false;

        app(PluginRegistrar::class)->channelDriver('fake_plugin', FakePluginChannelDriver::class);

        $registry = app(ChannelProviderRegistry::class);

        expect($registry->has('fake_plugin'))->toBeTrue()
            ->and($registry->resolve(driverKey: 'fake_plugin'))->toBeInstanceOf(FakePluginChannelDriver::class)
            ->and($registry->channels())->toContain('fake_channel');
    });

    it('routes queued sends to a plugin driver', function () {
        FakePluginChannelDriver::$sendCalled = false;
        FakePluginChannelDriver::$lastPayload = null;

        app(PluginRegistrar::class)->channelDriver('fake_plugin', FakePluginChannelDriver::class);

        $member = Member::factory()->create();

        Queue::fake();

        app(ChannelProviderRegistry::class)->send(new ChannelSendData(
            driverKey: 'fake_plugin',
            notifiableId: $member->id,
            notifiableType: Member::class,
            title: 'Plugin test',
            body: 'Hello from plugin driver.',
            config: ['api_key' => 'test-key'],
        ));

        Queue::assertPushed(SendChannelNotification::class, function (SendChannelNotification $job) {
            return $job->payload->driverKey === 'fake_plugin'
                && $job->queue === 'notifications';
        });

        $pushed = Queue::pushed(SendChannelNotification::class)->first();
        $pushed->handle(app(ChannelProviderRegistry::class));

        expect(FakePluginChannelDriver::$sendCalled)->toBeTrue()
            ->and(FakePluginChannelDriver::$lastPayload?->title)->toBe('Plugin test')
            ->and(FakePluginChannelDriver::$lastConfig)->toBe(['api_key' => 'test-key']);
    });

    it('exposes plugin driver configSchema with masked secrets', function () {
        app(PluginRegistrar::class)->channelDriver('fake_plugin', FakePluginChannelDriver::class);

        $driver = collect(app(ChannelProviderRegistry::class)->drivers())
            ->firstWhere('key', 'fake_plugin');

        expect($driver)->not->toBeNull()
            ->and(collect($driver->configSchema)->firstWhere('key', 'api_key')?->secret)->toBeTrue()
            ->and($driver->config['api_key'])->not->toBe('super-secret-token')
            ->and($driver->config['webhook_url'])->toBe('https://example.com/hook');
    });

    it('self-tests a plugin driver with supplied config', function () {
        app(PluginRegistrar::class)->channelDriver('fake_plugin', FakePluginChannelDriver::class);

        $registry = app(ChannelProviderRegistry::class);

        $failure = $registry->test(new TestChannelProviderData(
            channel: 'fake_channel',
            driverKey: 'fake_plugin',
            config: [],
        ));

        expect($failure->success)->toBeFalse()
            ->and($failure->message)->toContain('API key is required');

        $success = $registry->test(new TestChannelProviderData(
            channel: 'fake_channel',
            driverKey: 'fake_plugin',
            config: ['api_key' => 'present'],
        ));

        expect($success->success)->toBeTrue()
            ->and($success->driverKey)->toBe('fake_plugin');
    });
});

describe('ChannelProviderRegistry send queues', function () {
    it('dispatches email sends on the mail queue', function () {
        Queue::fake();

        app(ChannelProviderRegistry::class)->send(new ChannelSendData(
            driverKey: 'email',
            notifiableId: 1,
            notifiableType: Member::class,
            title: 'Subject',
            body: 'Body',
        ));

        Queue::assertPushed(SendChannelNotification::class, fn (SendChannelNotification $job) => $job->queue === 'mail');
    });

    it('dispatches database sends on the notifications queue', function () {
        Queue::fake();

        app(ChannelProviderRegistry::class)->send(new ChannelSendData(
            driverKey: 'database',
            notifiableId: 1,
            notifiableType: Member::class,
            title: 'Subject',
            body: 'Body',
        ));

        Queue::assertPushed(SendChannelNotification::class, fn (SendChannelNotification $job) => $job->queue === 'notifications');
    });
});

describe('driver self-tests', function () {
    it('email driver fails when mailer is log', function () {
        settings()->set('email.mailer', 'log');

        $result = app(ChannelProviderRegistry::class)->test(new TestChannelProviderData(
            channel: 'mail',
            recipient: 'test@example.com',
        ));

        expect($result->success)->toBeFalse()
            ->and($result->message)->toContain('log driver');
    });

    it('email driver fails without recipient', function () {
        settings()->setMany([
            'email.mailer' => 'smtp',
            'email.smtp_host' => 'smtp.example.com',
            'email.from_address' => 'noreply@example.com',
        ]);

        $result = app(ChannelProviderRegistry::class)->test(new TestChannelProviderData(
            channel: 'mail',
        ));

        expect($result->success)->toBeFalse()
            ->and($result->message)->toContain('recipient');
    });

    it('email driver sends a test email when configured', function () {
        (new EmailTemplateSeeder)->run();

        settings()->setMany([
            'email.mailer' => 'smtp',
            'email.smtp_host' => 'smtp.example.com',
            'email.from_address' => 'noreply@example.com',
        ]);

        Mail::fake();

        $result = app(ChannelProviderRegistry::class)->test(new TestChannelProviderData(
            channel: 'mail',
            recipient: 'recipient@example.com',
        ));

        expect($result->success)->toBeTrue()
            ->and($result->message)->toContain('recipient@example.com');

        Mail::assertSent(TemplatedEmail::class, fn ($mail) => $mail->hasTo('recipient@example.com'));
    });

    it('database driver passes self-test', function () {
        $result = app(ChannelProviderRegistry::class)->test(new TestChannelProviderData(
            channel: 'database',
        ));

        expect($result->success)->toBeTrue()
            ->and($result->channel)->toBe('database');
    });

    it('broadcast driver fails when broadcasting is disabled', function () {
        config(['broadcasting.default' => 'null']);

        $result = app(ChannelProviderRegistry::class)->test(new TestChannelProviderData(
            channel: 'broadcast',
        ));

        expect($result->success)->toBeFalse()
            ->and($result->message)->toContain('Broadcasting is disabled');
    });

    it('broadcast driver passes self-test with log connection', function () {
        config(['broadcasting.default' => 'log']);

        $result = app(ChannelProviderRegistry::class)->test(new TestChannelProviderData(
            channel: 'broadcast',
        ));

        expect($result->success)->toBeTrue()
            ->and($result->message)->toContain('log');
    });
});

describe('ChannelConfigMasker', function () {
    it('masks secret values and leaves non-secrets intact', function () {
        $schema = [
            new ChannelConfigFieldData('public', 'Public', 'string'),
            new ChannelConfigFieldData('secret', 'Secret', 'password', secret: true),
        ];

        $masked = ChannelConfigMasker::mask([
            'public' => 'visible',
            'secret' => 'my-long-api-key-value',
        ], $schema);

        expect($masked['public'])->toBe('visible')
            ->and($masked['secret'])->toBe('my••••••••••••ue');
    });
});
