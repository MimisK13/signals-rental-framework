<?php

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\AuditLog\Models\ActionLog;
use App\Domain\Availability\Contracts\AvailabilityResolutionProvider;
use App\Domain\Availability\Enums\AvailabilityResolution;
use App\Domain\Availability\Events\AvailabilityChanged;
use App\Domain\Availability\Shortages\Enums\WaitlistMonitorStatus;
use App\Domain\Availability\Shortages\Listeners\MatchWaitlistMonitors;
use App\Domain\Availability\Shortages\Models\ShortageWaitlistMonitor;
use App\Domain\Communications\Models\CommunicationLog;
use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Database\Seeders\NotificationTypeSeeder;
use App\Domain\Notifications\Jobs\SendNotification;
use App\Domain\Notifications\Listeners\NotificationDispatchListener;
use App\Domain\Notifications\Notifications\DomainEventNotification;
use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\ConvertToOrder;
use App\Domain\Projects\Opportunities\Actions\ConvertToQuotation;
use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Data\AddOpportunityItemData;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Enums\OpportunityState;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);

    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(NotificationTypeSeeder::class);

    Store::factory()->create(['is_default' => true]);

    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
});

it('dispatches opportunity.confirmed when an opportunity converts to order', function () {
    Queue::fake();

    $owner = Member::factory()->create();
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Notification wiring',
        'owned_by' => $owner->id,
    ]));

    $opportunity = Opportunity::query()->findOrFail($created->id);
    (new ConvertToQuotation)($opportunity);
    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => 'Line item',
        'quantity' => '1',
        'unit_price' => 1000,
    ]));
    (new ConvertToOrder)($opportunity->fresh());

    Queue::assertPushed(
        SendNotification::class,
        fn (SendNotification $job): bool => $job->notificationTypeKey === 'opportunity.confirmed'
            && $job->notificationClass === DomainEventNotification::class
            && $job->subjectId === $created->id,
    );
});

it('dispatches opportunity.created when a new opportunity is created', function () {
    Queue::fake();

    $owner = Member::factory()->create();
    (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Created notification',
        'owned_by' => $owner->id,
    ]));

    Queue::assertPushed(
        SendNotification::class,
        fn (SendNotification $job): bool => $job->notificationTypeKey === 'opportunity.created',
    );
});

it('dispatches shortage.waitlist.matched when a monitor matches', function () {
    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    Queue::fake();

    $store = Store::factory()->create(['timezone' => 'UTC']);
    $product = Product::factory()->rental()->bulk()->create();
    $owner = Member::factory()->create();
    $opportunity = Opportunity::factory()->create(['owned_by' => $owner->id]);
    $item = OpportunityItem::factory()->create([
        'opportunity_id' => $opportunity->id,
        'itemable_id' => $product->id,
    ]);

    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 10,
    ]);

    $monitor = ShortageWaitlistMonitor::factory()->create([
        'opportunity_item_id' => $item->id,
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_needed' => 2,
        'starts_at' => Carbon::parse('2026-07-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-07-05T17:00:00Z'),
        'status' => WaitlistMonitorStatus::Active->value,
    ]);

    app(MatchWaitlistMonitors::class)->handle(
        new AvailabilityChanged($product->id, $store->id),
    );

    Queue::assertPushed(
        SendNotification::class,
        fn (SendNotification $job): bool => $job->notificationTypeKey === 'shortage.waitlist.matched'
            && $job->recipientId === $owner->id
            && $job->subjectType === ShortageWaitlistMonitor::class
            && $job->subjectId === $monitor->id,
    );
});

it('does NOT dispatch notifications during a Verbs replay', function () {
    $owner = Member::factory()->create();
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Replay-safe notifications',
        'owned_by' => $owner->id,
    ]));

    $opportunity = Opportunity::query()->findOrFail($created->id);
    (new ConvertToQuotation)($opportunity);
    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => 'Line item',
        'quantity' => '1',
        'unit_price' => 1000,
    ]));
    (new ConvertToOrder)($opportunity->fresh());

    Notification::fake();
    Queue::fake();

    $logsBeforeReplay = CommunicationLog::query()->count();

    Verbs::replay();

    Notification::assertNothingSent();
    Queue::assertNothingPushed();
    expect(CommunicationLog::query()->count())->toBe($logsBeforeReplay);
});

it('still writes audit rows on replay even though notifications are skipped', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Audit survives replay']));
    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));

    $before = ActionLog::query()->where('auditable_id', $created->id)->count();
    expect($before)->toBeGreaterThan(0);

    Notification::fake();
    Queue::fake();

    Verbs::replay();

    expect(ActionLog::query()->where('auditable_id', $created->id)->count())->toBe($before);
});

it('no-ops for unknown audit actions', function () {
    Queue::fake();

    app(NotificationDispatchListener::class)->handle(
        new AuditableEvent(Member::factory()->create(), 'member.updated'),
    );

    Queue::assertNothingPushed();
});

it('no-ops when conditional mapping does not match', function () {
    Queue::fake();

    $opportunity = Opportunity::factory()->create([
        'state' => OpportunityState::Order->value,
        'status' => 0,
    ]);

    app(NotificationDispatchListener::class)->handle(
        new AuditableEvent(
            model: $opportunity,
            action: 'opportunity.status_changed',
            newValues: ['status' => 0],
        ),
    );

    Queue::assertNothingPushed();
});
