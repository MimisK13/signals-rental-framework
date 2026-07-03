<?php

use App\Actions\Notifications\TestChannelProvider;
use App\Contracts\Notifications\ChannelDriver;
use App\Data\Notifications\ChannelConfigFieldData;
use App\Data\Notifications\ChannelSendData;
use App\Data\Notifications\ChannelTestResultData;
use App\Data\Notifications\TestChannelProviderData;
use App\Mail\TemplatedEmail;
use App\Models\User;
use App\Services\Plugins\PluginRegistrar;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('self-tests the email channel for authorized users', function () {
    (new EmailTemplateSeeder)->run();
    $this->actingAs(User::factory()->owner()->create());

    settings()->setMany([
        'email.mailer' => 'smtp',
        'email.smtp_host' => 'smtp.example.com',
        'email.from_address' => 'noreply@example.com',
    ]);

    Mail::fake();

    $result = app(TestChannelProvider::class)(new TestChannelProviderData(
        channel: 'mail',
        recipient: 'admin@example.com',
    ));

    expect($result->success)->toBeTrue()
        ->and($result->driverKey)->toBe('email')
        ->and($result->channel)->toBe('mail');

    Mail::assertSent(TemplatedEmail::class);
});

it('self-tests the database channel for authorized users', function () {
    $this->actingAs(User::factory()->owner()->create());

    $result = app(TestChannelProvider::class)(new TestChannelProviderData(
        channel: 'database',
    ));

    expect($result->success)->toBeTrue()
        ->and($result->driverKey)->toBe('database');
});

it('self-tests the broadcast channel for authorized users', function () {
    config(['broadcasting.default' => 'log']);
    $this->actingAs(User::factory()->owner()->create());

    $result = app(TestChannelProvider::class)(new TestChannelProviderData(
        channel: 'broadcast',
    ));

    expect($result->success)->toBeTrue()
        ->and($result->driverKey)->toBe('broadcast');
});

it('returns sensible failure messages from the registry', function () {
    $this->actingAs(User::factory()->owner()->create());
    settings()->set('email.mailer', 'log');

    $result = app(TestChannelProvider::class)(new TestChannelProviderData(
        channel: 'mail',
        recipient: 'admin@example.com',
    ));

    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('log driver');
});

it('rejects unauthorized users', function () {
    $this->actingAs(User::factory()->create());

    app(TestChannelProvider::class)(new TestChannelProviderData(
        channel: 'database',
    ));
})->throws(AuthorizationException::class);

it('allows explicit driver key override', function () {
    config(['broadcasting.default' => 'log']);
    $this->actingAs(User::factory()->owner()->create());

    $result = app(TestChannelProvider::class)(new TestChannelProviderData(
        channel: 'broadcast',
        driverKey: 'broadcast',
    ));

    expect($result->success)->toBeTrue()
        ->and($result->driverKey)->toBe('broadcast');
});

class TestActionPluginChannelDriver implements ChannelDriver
{
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveConfig(): array
    {
        return ['api_key' => 'configured'];
    }

    public function send(ChannelSendData $payload): void {}

    public function test(TestChannelProviderData $data): ChannelTestResultData
    {
        if (($data->config['api_key'] ?? '') === '') {
            return ChannelTestResultData::failed($this, 'API key is required.');
        }

        return ChannelTestResultData::passed($this, 'Fake plugin channel test succeeded via action.');
    }
}

it('self-tests a plugin-registered channel through the action after validation', function () {
    app(PluginRegistrar::class)->channelDriver('fake_plugin', TestActionPluginChannelDriver::class);
    $this->actingAs(User::factory()->owner()->create());

    $data = TestChannelProviderData::from([
        'channel' => 'fake_channel',
        'driverKey' => 'fake_plugin',
        'config' => ['api_key' => 'present'],
    ]);

    $result = app(TestChannelProvider::class)($data);

    expect($result->success)->toBeTrue()
        ->and($result->driverKey)->toBe('fake_plugin')
        ->and($result->channel)->toBe('fake_channel')
        ->and($result->message)->toContain('via action');
});

it('rejects unknown channels with validation errors', function () {
    expect(fn () => TestChannelProviderData::validate(['channel' => 'sms']))
        ->toThrow(ValidationException::class);

    try {
        TestChannelProviderData::validate(['channel' => 'sms']);
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('channel');
    }
});
