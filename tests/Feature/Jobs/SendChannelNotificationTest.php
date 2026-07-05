<?php

use App\Contracts\Notifications\ChannelDriver;
use App\Data\Notifications\ChannelConfigFieldData;
use App\Data\Notifications\ChannelSendData;
use App\Data\Notifications\ChannelTestResultData;
use App\Data\Notifications\TestChannelProviderData;
use App\Jobs\SendChannelNotification;
use App\Mail\TemplatedEmail;
use App\Models\Member;
use App\Services\Notifications\ChannelProviderRegistry;
use App\Services\Plugins\PluginRegistrar;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

class SendTrackingChannelDriver implements ChannelDriver
{
    public static int $sendCount = 0;

    public function key(): string
    {
        return 'send_tracker';
    }

    public function channel(): string
    {
        return 'send_tracker_channel';
    }

    public function label(): string
    {
        return 'Send Tracker';
    }

    /**
     * @return list<ChannelConfigFieldData>
     */
    public function configSchema(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveConfig(): array
    {
        return [];
    }

    public function send(ChannelSendData $payload): void
    {
        self::$sendCount++;
    }

    public function test(TestChannelProviderData $data): ChannelTestResultData
    {
        return ChannelTestResultData::passed($this, 'ok');
    }
}

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    SendTrackingChannelDriver::$sendCount = 0;
});

it('executes the resolved driver when handled', function () {
    app(PluginRegistrar::class)->channelDriver('send_tracker', SendTrackingChannelDriver::class);

    $job = new SendChannelNotification(new ChannelSendData(
        driverKey: 'send_tracker',
        notifiableId: 1,
        notifiableType: Member::class,
        title: 'Queued',
        body: 'Body',
    ));

    $job->handle(app(ChannelProviderRegistry::class));

    expect(SendTrackingChannelDriver::$sendCount)->toBe(1);
});

it('retries with escalating backoff configuration', function () {
    $job = new SendChannelNotification(new ChannelSendData(
        driverKey: 'database',
        notifiableId: 1,
        notifiableType: Member::class,
        title: 'Queued',
        body: 'Body',
    ));

    expect($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([30, 120, 600]);
});

it('delivers database notifications when the job runs', function () {
    $member = Member::factory()->create();

    $job = new SendChannelNotification(new ChannelSendData(
        driverKey: 'database',
        notifiableId: $member->id,
        notifiableType: Member::class,
        title: 'Queued notification',
        body: 'Delivered via job.',
        data: ['source' => 'job-test'],
    ));

    $job->handle(app(ChannelProviderRegistry::class));

    expect($member->notifications()->count())->toBe(1)
        ->and($member->notifications()->first()->data['title'] ?? null)->toBe('Queued notification');
});

it('delivers email when the job runs', function () {
    (new EmailTemplateSeeder)->run();

    settings()->setMany([
        'email.mailer' => 'smtp',
        'email.smtp_host' => 'smtp.example.com',
        'email.from_address' => 'noreply@example.com',
    ]);

    Mail::fake();

    $member = Member::factory()->contact()->create();
    $member->emails()->create(['address' => 'member@example.com', 'is_primary' => true]);

    $job = new SendChannelNotification(new ChannelSendData(
        driverKey: 'email',
        notifiableId: $member->id,
        notifiableType: Member::class,
        title: 'Queued email',
        body: 'Email body via job.',
    ));

    $job->handle(app(ChannelProviderRegistry::class));

    Mail::assertSent(TemplatedEmail::class, fn ($mail) => $mail->hasTo('member@example.com'));
});

it('rethrows delivery failures for queue retry', function () {
    settings()->set('email.mailer', 'log');

    $job = new SendChannelNotification(new ChannelSendData(
        driverKey: 'email',
        notifiableId: 1,
        notifiableType: Member::class,
        title: 'Will fail',
        body: 'Body',
        data: ['recipient' => 'fail@example.com'],
    ));

    expect(fn () => $job->handle(app(ChannelProviderRegistry::class)))
        ->toThrow(RuntimeException::class);
});

it('is dispatched through the registry onto the correct queue', function () {
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
