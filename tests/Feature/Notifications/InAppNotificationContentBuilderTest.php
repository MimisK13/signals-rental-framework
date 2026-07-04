<?php

use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\CreateOpportunityData;
use App\Events\AuditableEvent;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\NotificationType;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\Notifications\InAppNotificationContentBuilder;
use Database\Seeders\NotificationTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(NotificationTypeSeeder::class);

    $this->builder = app(InAppNotificationContentBuilder::class);
});

test('opportunity.created body includes opportunity subject and member name', function () {
    $member = Member::factory()->organisation()->create(['name' => 'Cartwright LLC']);
    $opportunity = Opportunity::factory()->create([
        'subject' => 'Arena Tour Production Package',
        'member_id' => $member->id,
    ]);

    $type = NotificationType::query()->where('key', 'opportunity.created')->firstOrFail();
    $event = new AuditableEvent($opportunity, 'opportunity.created');

    $content = $this->builder->build($event, $type);

    expect($content['title'])->toBe('Opportunity Created')
        ->and($content['body'])->toContain('Arena Tour Production Package')
        ->and($content['body'])->toContain('Cartwright LLC')
        ->and($content['body'])->not->toBe($type->description);
});

test('invoice.overdue body includes invoice number and formatted total', function () {
    $member = Member::factory()->organisation()->create(['name' => 'Cartwright LLC']);
    $invoice = Invoice::factory()->create([
        'number' => 'INV-2042',
        'member_id' => $member->id,
        'total' => 125_000,
        'currency_code' => 'GBP',
    ]);

    $type = NotificationType::query()->where('key', 'invoice.overdue')->firstOrFail();
    $event = new AuditableEvent($invoice, 'invoice.overdue');

    $content = $this->builder->build($event, $type);

    expect($content['body'])->toContain('INV-2042')
        ->and($content['body'])->toContain('1250.00')
        ->and($content['body'])->toContain('Cartwright LLC')
        ->and($content['body'])->not->toBe($type->description);
});

test('falls back to notification type description when context is missing', function () {
    $member = Member::factory()->create();
    $type = NotificationType::query()->where('key', 'opportunity.created')->firstOrFail();
    $event = new AuditableEvent($member, 'opportunity.created');

    $content = $this->builder->build($event, $type);

    expect($content['body'])->toBe('Sent when a new opportunity is created.');
});

test('creating an opportunity stores an event-specific in-app notification body', function () {
    $this->actingAs(User::factory()->owner()->create());

    $customer = Member::factory()->organisation()->create(['name' => 'Cartwright LLC']);
    $owner = Member::factory()->create();

    (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Arena Tour Production Package',
        'member_id' => $customer->id,
        'owned_by' => $owner->id,
    ]));

    $notification = $owner->fresh()->notifications()->firstOrFail();
    /** @var array<string, mixed> $data */
    $data = $notification->data;

    expect($data['body'])->toContain('Arena Tour Production Package')
        ->and($data['body'])->toContain('Cartwright LLC')
        ->and($data['body'])->not->toBe('Sent when a new opportunity is created.');
});
