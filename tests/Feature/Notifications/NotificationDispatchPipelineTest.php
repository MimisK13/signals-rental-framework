<?php

use App\Actions\Notifications\DispatchNotification;
use App\Actions\Notifications\LogCommunication;
use App\Data\Notifications\DispatchNotificationData;
use App\Jobs\SendNotification;
use App\Models\CommunicationLog;
use App\Models\Member;
use App\Models\NotificationType;
use App\Models\Opportunity;
use App\Notifications\FailingTestPipelineNotification;
use App\Notifications\TestPipelineNotification;
use App\Services\Notifications\NotificationChannelResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

describe('notification dispatch pipeline', function () {
    it('dispatches end-to-end with in_app and email and writes communication_log rows', function () {
        Mail::fake();

        $recipient = Member::factory()->create();
        $recipient->emails()->create(['address' => 'pipeline@example.com', 'is_primary' => true]);

        $owner = Member::factory()->create();
        $opportunity = Opportunity::factory()->create(['owned_by' => $owner->id]);

        $type = NotificationType::factory()->create([
            'key' => 'pipeline.test',
            'default_channels' => ['in_app', 'email'],
            'default_recipients' => [['type' => 'specific_member', 'member_id' => $recipient->id]],
        ]);

        app(DispatchNotification::class)(new DispatchNotificationData(
            notificationTypeKey: $type->key,
            notificationClass: TestPipelineNotification::class,
            subject: $opportunity,
            payload: [
                'title' => 'Opportunity confirmed',
                'body' => 'Opportunity #123 has been confirmed.',
            ],
            recipientRules: [['type' => 'specific_member', 'member_id' => $recipient->id]],
        ));

        $logs = CommunicationLog::query()
            ->where('notification_type_key', $type->key)
            ->orderBy('channel')
            ->get();

        expect($logs)->toHaveCount(2)
            ->and($logs->pluck('channel')->sort()->values()->all())->toBe(['email', 'in_app'])
            ->and($logs->every(fn (CommunicationLog $log): bool => $log->status === 'sent'))->toBeTrue()
            ->and($logs->first()->subject_type)->toBe(Opportunity::class)
            ->and($logs->first()->notifiable_id)->toBe($recipient->id);

        $emailLog = $logs->firstWhere('channel', 'email');

        expect($emailLog)->not->toBeNull()
            ->and($emailLog->recipient_address)->toBe('pipeline@example.com')
            ->and($emailLog->subject_line)->toBe('Opportunity confirmed');

        expect($recipient->fresh()->notifications)->toHaveCount(1);
    });

    it('does not dispatch when the surrounding transaction rolls back', function () {
        Mail::fake();
        Http::fake();

        $recipient = Member::factory()->create();
        $recipient->emails()->create(['address' => 'rollback@example.com', 'is_primary' => true]);
        $opportunity = Opportunity::factory()->create();

        $type = NotificationType::factory()->create([
            'key' => 'pipeline.rollback',
            'default_channels' => ['in_app', 'email'],
        ]);

        $thrown = false;

        try {
            DB::transaction(function () use ($type, $opportunity, $recipient): void {
                app(DispatchNotification::class)(new DispatchNotificationData(
                    notificationTypeKey: $type->key,
                    notificationClass: TestPipelineNotification::class,
                    subject: $opportunity,
                    recipientRules: [['type' => 'specific_member', 'member_id' => $recipient->id]],
                ));

                throw new RuntimeException('forced rollback after dispatch');
            });
        } catch (RuntimeException $e) {
            $thrown = $e->getMessage() === 'forced rollback after dispatch';
        }

        expect($thrown)->toBeTrue()
            ->and(CommunicationLog::query()->where('notification_type_key', $type->key)->exists())->toBeFalse()
            ->and($recipient->fresh()->notifications)->toHaveCount(0);

        Mail::assertNothingSent();
    });

    it('fans out one SendNotification job per recipient for independent retry', function () {
        Queue::fake();

        $first = Member::factory()->create();
        $second = Member::factory()->create();
        $opportunity = Opportunity::factory()->create();

        $type = NotificationType::factory()->create([
            'key' => 'pipeline.fanout',
            'default_channels' => ['in_app'],
        ]);

        app(DispatchNotification::class)(new DispatchNotificationData(
            notificationTypeKey: $type->key,
            notificationClass: TestPipelineNotification::class,
            subject: $opportunity,
            recipientRules: [
                ['type' => 'specific_member', 'member_id' => $first->id],
                ['type' => 'specific_member', 'member_id' => $second->id],
            ],
        ));

        Queue::assertPushed(SendNotification::class, 2);

        Queue::assertPushed(SendNotification::class, function (SendNotification $job) use ($first): bool {
            return $job->recipientId === $first->id;
        });

        Queue::assertPushed(SendNotification::class, function (SendNotification $job) use ($second): bool {
            return $job->recipientId === $second->id;
        });
    });

    it('retries failed recipients independently without affecting other jobs', function () {
        Mail::fake();

        $failingRecipient = Member::factory()->create();
        $successRecipient = Member::factory()->create();
        $successRecipient->emails()->create(['address' => 'success@example.com', 'is_primary' => true]);
        $opportunity = Opportunity::factory()->create();

        $type = NotificationType::factory()->create([
            'key' => 'pipeline.retry',
            'default_channels' => ['email'],
        ]);

        $failingJob = new SendNotification(
            notificationClass: FailingTestPipelineNotification::class,
            notificationTypeKey: $type->key,
            recipientId: $failingRecipient->id,
            subjectType: Opportunity::class,
            subjectId: $opportunity->id,
            payload: ['failing_recipient_id' => $failingRecipient->id],
            logicalChannels: ['email'],
        );

        $successJob = new SendNotification(
            notificationClass: TestPipelineNotification::class,
            notificationTypeKey: $type->key,
            recipientId: $successRecipient->id,
            subjectType: Opportunity::class,
            subjectId: $opportunity->id,
            payload: ['title' => 'Success', 'body' => 'Delivered'],
            logicalChannels: ['email'],
        );

        try {
            $failingJob->handle(
                app(NotificationChannelResolver::class),
                app(LogCommunication::class),
            );
        } catch (RuntimeException) {
            // Expected for the failing recipient.
        }

        $successJob->handle(
            app(NotificationChannelResolver::class),
            app(LogCommunication::class),
        );

        $failedLog = CommunicationLog::query()
            ->where('notifiable_id', $failingRecipient->id)
            ->first();

        $successLog = CommunicationLog::query()
            ->where('notifiable_id', $successRecipient->id)
            ->first();

        expect($failedLog)->not->toBeNull()
            ->and($failedLog->status)->toBe('failed')
            ->and($successLog)->not->toBeNull()
            ->and($successLog->status)->toBe('sent')
            ->and($successLog->recipient_address)->toBe('success@example.com');
    });
});

describe('SendNotification job contract', function () {
    it('uses afterCommit, three tries, and escalating backoff', function () {
        $job = new SendNotification(
            notificationClass: TestPipelineNotification::class,
            notificationTypeKey: 'pipeline.contract',
            recipientId: 1,
            subjectType: Opportunity::class,
            subjectId: 1,
        );

        expect($job->tries)->toBe(3)
            ->and($job->backoff())->toBe([30, 120, 600])
            ->and($job->middleware())->toHaveCount(1);
    });
});
