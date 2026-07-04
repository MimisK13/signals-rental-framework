<?php

use App\Enums\NotificationAudience;
use App\Models\Member;
use App\Models\MemberRelationship;
use App\Models\NotificationSetting;
use App\Models\NotificationType;
use App\Services\NotificationRegistry;
use Database\Seeders\NotificationTypeSeeder;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

describe('member notifiable routing', function () {
    it('routes mail notifications to the primary email address', function () {
        $member = Member::factory()->contact()->create();
        $member->emails()->create(['address' => 'secondary@example.com', 'is_primary' => false]);
        $member->emails()->create(['address' => 'primary@example.com', 'is_primary' => true]);

        expect($member->routeNotificationForMail())->toBe('primary@example.com');
    });

    it('falls back to the first stored email when none is marked primary', function () {
        $member = Member::factory()->contact()->create();
        $member->emails()->create(['address' => 'first@example.com', 'is_primary' => false]);
        $member->emails()->create(['address' => 'second@example.com', 'is_primary' => false]);

        expect($member->routeNotificationForMail())->toBe('first@example.com');
    });

    it('returns null when the member has no email addresses', function () {
        $member = Member::factory()->contact()->create();

        expect($member->routeNotificationForMail())->toBeNull();
    });

    it('falls back to the primary contact email for organisations without a direct address', function () {
        $organisation = Member::factory()->organisation()->create();
        $contact = Member::factory()->contact()->create();
        $contact->emails()->create(['address' => 'billing-contact@example.com', 'is_primary' => true]);

        MemberRelationship::factory()->primary()->create([
            'member_id' => $contact->id,
            'related_member_id' => $organisation->id,
        ]);

        expect($organisation->fresh()->routeNotificationForMail())->toBe('billing-contact@example.com');
    });

    it('delivers a mail notification to the member primary email route', function () {
        NotificationFacade::fake();

        $member = Member::factory()->contact()->create();
        $member->emails()->create(['address' => 'notify@example.com', 'is_primary' => true]);

        $notification = new class extends Notification
        {
            public function via(object $notifiable): array
            {
                return ['mail'];
            }

            public function toMail(object $notifiable): MailMessage
            {
                return (new MailMessage)->line('Test notification');
            }
        };

        $member->notify($notification);

        NotificationFacade::assertSentTo($member, $notification::class);
    });

    it('persists communication preferences on members', function () {
        $member = Member::factory()->contact()->create([
            'communication_preferences' => [
                'opt_out' => ['invoice.created'],
            ],
        ]);

        expect($member->refresh()->communication_preferences)->toBe([
            'opt_out' => ['invoice.created'],
        ]);
    });
});

describe('notification type seeder', function () {
    it('seeds the full core notification catalogue with audience and data schema', function () {
        $this->seed(NotificationTypeSeeder::class);

        $confirmed = NotificationType::query()->where('key', 'opportunity.confirmed')->first();
        $invoice = NotificationType::query()->where('key', 'invoice.created')->first();
        $document = NotificationType::query()->where('key', 'document.finalised')->first();
        $shortage = NotificationType::query()->where('key', 'shortage.detected')->first();

        expect($confirmed)->not->toBeNull()
            ->and($confirmed->audience)->toBe(NotificationAudience::Both)
            ->and($confirmed->data_schema)->toBeArray()
            ->and($invoice->audience)->toBe(NotificationAudience::External)
            ->and($document->category)->toBe('Documents')
            ->and($shortage->category)->toBe('Shortages');
    });

    it('is idempotent when run multiple times', function () {
        $this->seed(NotificationTypeSeeder::class);
        $firstCount = NotificationType::count();

        $this->seed(NotificationTypeSeeder::class);

        expect(NotificationType::count())->toBe($firstCount)
            ->and(NotificationType::query()->where('key', 'opportunity.confirmed')->count())->toBe(1);
    });

    it('registers the expected number of core notification types', function () {
        $this->seed(NotificationTypeSeeder::class);

        expect(NotificationType::count())->toBe(count(NotificationTypeSeeder::types()));
    });
});

describe('notification registry sync', function () {
    it('preserves tenant customised notification settings on re-sync', function () {
        $registry = app(NotificationRegistry::class);
        $registry->registerMany(NotificationTypeSeeder::types());
        $registry->syncToDatabase();

        $type = NotificationType::query()->where('key', 'opportunity.confirmed')->firstOrFail();
        $setting = NotificationSetting::factory()->create([
            'notification_type_id' => $type->id,
            'channels' => ['database'],
            'channel_config' => [
                'mail' => ['provider_id' => 99, 'template_id' => 42],
            ],
            'recipient_rules' => [['type' => 'specific_member', 'member_id' => 7]],
            'is_enabled' => false,
        ]);

        $registry->register('opportunity.confirmed', array_merge(
            NotificationTypeSeeder::types()['opportunity.confirmed'],
            ['description' => 'Updated from code registry.'],
        ));
        $registry->syncToDatabase();

        $type->refresh();
        $setting->refresh();

        expect($type->description)->toBe('Updated from code registry.')
            ->and($setting->channels)->toBe(['database'])
            ->and($setting->channel_config)->toBe([
                'mail' => ['provider_id' => 99, 'template_id' => 42],
            ])
            ->and($setting->recipient_rules)->toBe([['type' => 'specific_member', 'member_id' => 7]])
            ->and($setting->is_enabled)->toBeFalse();
    });
});

describe('horizon notification queues', function () {
    it('registers notifications and communications queues for horizon workers', function () {
        $defaults = config('horizon.defaults.supervisor-notifications.queue');

        expect($defaults)->toBe(['notifications', 'communications']);
    });
});
