<?php

use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\ShortageWaitlistMonitor;
use App\Models\Store;
use App\Models\User;
use App\Services\Notifications\RecipientResolvers\ContactRecipientResolver;
use App\Services\Notifications\RecipientResolvers\RoleRecipientResolver;
use App\Services\Notifications\RecipientResolvers\SpecificMemberRecipientResolver;
use App\Services\Notifications\RecipientResolvers\SubjectOwnerRecipientResolver;
use App\Data\Notifications\NotificationContextData;
use App\Models\NotificationType;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->type = NotificationType::factory()->create();
});

function recipientContext(object $subject): NotificationContextData
{
    return new NotificationContextData(
        type: test()->type,
        subject: $subject,
    );
}

it('contact resolver reads member_id from generic subject objects', function () {
    $contact = Member::factory()->contact()->create();
    $subject = (object) ['member_id' => $contact->id];

    $recipients = app(ContactRecipientResolver::class)->resolve([], recipientContext($subject));

    expect($recipients)->toHaveCount(1)
        ->and($recipients->first()->is($contact))->toBeTrue();
});

it('specific member resolver ignores non-numeric member ids', function () {
    $recipients = app(SpecificMemberRecipientResolver::class)->resolve(
        ['member_id' => 'not-a-number'],
        recipientContext(Opportunity::factory()->create()),
    );

    expect($recipients)->toBeEmpty();
});

it('role resolver returns empty collections for missing roles and empty assignments', function () {
    $context = recipientContext(Opportunity::factory()->create());

    expect(app(RoleRecipientResolver::class)->resolve(['role' => ''], $context))->toBeEmpty()
        ->and(app(RoleRecipientResolver::class)->resolve(['role' => 'Missing Role'], $context))->toBeEmpty();

    $role = Role::create(['name' => 'Empty Role', 'guard_name' => 'web']);
    User::factory()->create(['member_id' => null])->assignRole($role);

    expect(app(RoleRecipientResolver::class)->resolve(['role' => 'Empty Role'], $context))->toBeEmpty();
});

it('subject owner resolver resolves waitlist monitor owners via opportunity items', function () {
    $owner = Member::factory()->create();
    $opportunity = Opportunity::factory()->create(['owned_by' => $owner->id]);
    $item = OpportunityItem::factory()->for($opportunity)->create([
        'item_type' => 'product',
        'itemable_type' => Product::class,
        'itemable_id' => Product::factory()->create()->id,
    ]);
    $monitor = ShortageWaitlistMonitor::factory()->create([
        'opportunity_item_id' => $item->id,
    ]);

    $recipients = app(SubjectOwnerRecipientResolver::class)->resolve([], recipientContext($monitor));

    expect($recipients)->toHaveCount(1)
        ->and($recipients->first()->is($owner))->toBeTrue();
});

it('subject owner resolver returns empty when the waitlist monitor has no opportunity item', function () {
    $monitor = ShortageWaitlistMonitor::factory()->create(['opportunity_item_id' => null]);

    expect(app(SubjectOwnerRecipientResolver::class)->resolve([], recipientContext($monitor)))->toBeEmpty();
});

it('subject owner resolver returns empty when the linked opportunity item is missing', function () {
    $item = OpportunityItem::factory()->create();
    $monitor = ShortageWaitlistMonitor::factory()->create(['opportunity_item_id' => $item->id]);
    $item->delete();

    expect(app(SubjectOwnerRecipientResolver::class)->resolve([], recipientContext($monitor->fresh())))->toBeEmpty();
});
