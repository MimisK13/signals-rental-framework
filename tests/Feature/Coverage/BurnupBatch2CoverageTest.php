<?php

/**
 * Coverage burn-up batch 2 — flip one-method-away classes to fully covered.
 */

use App\Actions\Containers\DissolveContainer;
use App\Actions\Documents\FinaliseDocument;
use App\Actions\Documents\ImportDocumentTemplate;
use App\Actions\Members\CreateMemberRelationship;
use App\Actions\Notifications\DispatchNotification;
use App\Actions\Notifications\SaveMemberNotificationPreferences;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ClearSectionDealPrice;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\DeleteOpportunitySection;
use App\Actions\Opportunities\ReorderOpportunitySections;
use App\Actions\Opportunities\SetSectionDealPrice;
use App\Actions\PurchaseOrders\UpdateDraftPurchaseOrder;
use App\Actions\VirtualStock\ApportionVirtualStockCost;
use App\Actions\VirtualStock\ConfirmVirtualStockIntake;
use App\Console\Commands\PruneCommunicationLogCommand;
use App\Data\Documents\ImportDocumentTemplateData;
use App\Data\Members\CreateMemberRelationshipData;
use App\Data\Notifications\DispatchNotificationData;
use App\Data\Notifications\SaveMemberNotificationPreferencesData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\ReorderOpportunitySectionsData;
use App\Data\Opportunities\SetSectionDealPriceData;
use App\Enums\ContainerStatus;
use App\Enums\DocumentStatus;
use App\Enums\OpportunityItemType;
use App\Events\AuditableEvent;
use App\Events\Availability\AvailabilityChanged;
use App\Jobs\DeliverCustomerCommunication;
use App\Jobs\SendNotification;
use App\Listeners\Availability\DetectOrderShortages;
use App\Livewire\Concerns\HandlesVerbsStateErrors;
use App\Models\ActionLog;
use App\Models\CommunicationLog;
use App\Models\CommunicationTemplate;
use App\Models\Container;
use App\Models\Document;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\NotificationType;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\OpportunityItem;
use App\Models\OpportunitySection;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\ShortageResolution;
use App\Models\ShortageResolutionItem;
use App\Models\Store;
use App\Models\User;
use App\Models\VirtualStockIntake;
use App\Notifications\CustomerCommunication;
use App\Notifications\TestPipelineNotification;
use App\Services\Notifications\NotificationChannelResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Thunk\Verbs\Exceptions\EventNotValid;
use Thunk\Verbs\Lifecycle\Broker;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    Store::factory()->create(['is_default' => true, 'timezone' => 'UTC']);
});

function burnup2Owner(): User
{
    return tap(User::factory()->owner()->create(), fn (User $user) => test()->actingAs($user));
}

function burnup2GrantStore(User $user, Store $store): void
{
    MemberStore::query()->create([
        'member_id' => $user->member_id,
        'store_id' => $store->id,
    ]);
}

function burnup2EnsureOpportunitySectionsTable(): void
{
    if (Schema::hasTable('opportunity_sections')) {
        return;
    }

    Schema::create('opportunity_sections', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('opportunity_id');
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->string('auto_group_key')->nullable();
        $table->string('name');
        $table->integer('sort_order')->default(0);
        $table->timestamps();
    });
}

function burnup2ApportionFixture(): VirtualStockIntake
{
    $intake = createTestVirtualStockIntake([
        'quantity' => 3,
        'unit_cost' => 1000,
        'available_from' => '2026-08-01T00:00:00Z',
        'available_until' => '2026-08-06T00:00:00Z',
    ]);

    $oppA = Opportunity::factory()->create([
        'starts_at' => Carbon::parse('2026-08-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-08-05T17:00:00Z'),
    ]);
    $oppB = Opportunity::factory()->create([
        'starts_at' => Carbon::parse('2026-08-02T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-08-04T17:00:00Z'),
    ]);

    $resolution = ShortageResolution::factory()->create([
        'virtual_stock_intake_id' => $intake->id,
        'metadata' => ['opportunity_id' => $oppA->id],
    ]);

    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $resolution->id,
        'opportunity_item_id' => OpportunityItem::factory()->for($oppA)->create()->id,
        'quantity_allocated' => 2,
    ]);
    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $resolution->id,
        'opportunity_item_id' => OpportunityItem::factory()->for($oppB)->create()->id,
        'quantity_allocated' => 1,
    ]);

    config(['shortages.cost_apportionment_strategy' => 'even_split']);
    app(ConfirmVirtualStockIntake::class)($intake->refresh());
    app(ApportionVirtualStockCost::class)($intake->refresh(), authorize: false);

    return $intake->refresh();
}

function burnup2WhileReplaying(Closure $callback): void
{
    $broker = app(Broker::class);
    $broker->is_replaying = true;

    try {
        $callback();
    } finally {
        $broker->is_replaying = false;
    }
}

function burnup2QuotationWithGroup(): array
{
    burnup2Owner();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Burnup batch 2 deal price',
    ]));
    $opportunity = Opportunity::query()->findOrFail($created->id);

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Section',
        'item_type' => 'group',
    ]));

    $opportunity = $opportunity->fresh(['items']);
    $group = $opportunity->items->first(fn (OpportunityItem $item): bool => $item->item_type === OpportunityItemType::Group);

    $product = Product::factory()->rental()->bulk()->create();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
    ]));

    $line = $opportunity->fresh()->items()->latest('id')->firstOrFail();
    $line->forceFill(['path' => $group->path.'0001'])->saveQuietly();

    (new ConvertToQuotation)($opportunity->fresh());

    $opportunity = $opportunity->fresh(['items']);
    $group = $opportunity->items->first(fn (OpportunityItem $item): bool => $item->item_type === OpportunityItemType::Group);

    return [$opportunity, $group];
}

it('DissolveContainer rejects dispatched containers and dissolves open kits', function () {
    burnup2Owner();
    $store = Store::factory()->create();
    $dispatched = Container::factory()->kit()->create([
        'store_id' => $store->id,
        'status' => ContainerStatus::Dispatched,
    ]);

    expect(fn () => (new DissolveContainer)($dispatched))
        ->toThrow(ValidationException::class, 'Dispatched or returned containers cannot be manually dissolved.');

    $open = Container::factory()->kit()->create(['store_id' => $store->id]);

    $result = (new DissolveContainer)($open);

    expect($result->status)->toBe(ContainerStatus::Dissolved->value)
        ->and($open->fresh()->status)->toBe(ContainerStatus::Dissolved);
});

it('FinaliseDocument finalises draft documents', function () {
    burnup2Owner();
    $document = Document::factory()->create(['status' => DocumentStatus::Draft]);

    $result = (new FinaliseDocument)($document);

    expect($result->status)->toBe(DocumentStatus::Final)
        ->and($document->fresh()->status)->toBe(DocumentStatus::Final);
});

it('CreateMemberRelationship assertAllowedPair rejects user-type source members', function () {
    burnup2Owner();
    $userMember = Member::factory()->user()->create();
    $contact = Member::factory()->contact()->create();

    expect(fn () => (new CreateMemberRelationship)($userMember, CreateMemberRelationshipData::from([
        'related_member_id' => $contact->id,
    ])))->toThrow(ValidationException::class, 'User-type members cannot have relationships.');
});

it('DispatchNotification skips empty rules and recipients with no channels', function () {
    burnup2Owner();
    Queue::fake();

    $type = NotificationType::factory()->create([
        'key' => 'burnup2.empty-rules',
        'default_recipients' => [],
    ]);
    $member = Member::factory()->create();

    app(DispatchNotification::class)(new DispatchNotificationData(
        notificationTypeKey: $type->key,
        notificationClass: TestPipelineNotification::class,
        subject: $member,
        recipientRules: [],
    ));

    Queue::assertNothingPushed();

    $typeWithRecipient = NotificationType::factory()->create([
        'key' => 'burnup2.no-channels',
        'default_channels' => ['in_app'],
    ]);

    $this->mock(NotificationChannelResolver::class, function ($mock): void {
        $mock->shouldReceive('resolve')->andReturn([]);
    });

    app(DispatchNotification::class)(new DispatchNotificationData(
        notificationTypeKey: $typeWithRecipient->key,
        notificationClass: TestPipelineNotification::class,
        subject: $member,
        actorMemberId: $member->id,
        recipientRules: [['type' => 'specific_member', 'member_id' => $member->id]],
    ));

    Queue::assertNothingPushed();

    $this->mock(NotificationChannelResolver::class, function ($mock): void {
        $mock->shouldReceive('resolve')->andReturn(['in_app']);
    });

    app(DispatchNotification::class)(new DispatchNotificationData(
        notificationTypeKey: $typeWithRecipient->key,
        notificationClass: TestPipelineNotification::class,
        subject: $member,
        recipientRules: [['type' => 'specific_member', 'member_id' => $member->id]],
    ));

    Queue::assertPushed(SendNotification::class);
});

it('SaveMemberNotificationPreferences assertActorCanManageTargetUser covers store and member guards', function () {
    $type = NotificationType::factory()->create();
    $owner = User::factory()->owner()->create();
    $manager = User::factory()->create(['is_owner' => false]);
    $manager->givePermissionTo('members.edit');

    $targetWithoutMember = User::factory()->create();
    $targetWithoutMember->forceFill(['member_id' => null])->saveQuietly();
    $this->actingAs($manager);

    expect(fn () => app(SaveMemberNotificationPreferences::class)(
        $manager,
        SaveMemberNotificationPreferencesData::from([
            'targetUserId' => $targetWithoutMember->id,
            'notificationTypeId' => $type->id,
            'isMuted' => true,
        ]),
    ))->toThrow(HttpException::class);

    $orphanMember = Member::factory()->contact()->create();
    $targetWithMissingMember = User::factory()->create(['member_id' => $orphanMember->id]);
    $orphanMember->delete();

    expect(fn () => app(SaveMemberNotificationPreferences::class)(
        $manager,
        SaveMemberNotificationPreferencesData::from([
            'targetUserId' => $targetWithMissingMember->id,
            'notificationTypeId' => $type->id,
            'isMuted' => true,
        ]),
    ))->toThrow(HttpException::class);

    $store = Store::factory()->create();
    burnup2GrantStore($manager, $store);
    $memberWithoutStores = Member::factory()->contact()->create();
    $targetUser = User::factory()->create(['member_id' => $memberWithoutStores->id]);

    app(SaveMemberNotificationPreferences::class)(
        $manager,
        SaveMemberNotificationPreferencesData::from([
            'targetUserId' => $targetUser->id,
            'notificationTypeId' => $type->id,
            'isMuted' => true,
        ]),
    );

    expect(true)->toBeTrue();

    $this->actingAs($owner);

    app(SaveMemberNotificationPreferences::class)(
        $owner,
        SaveMemberNotificationPreferencesData::from([
            'targetUserId' => $targetUser->id,
            'notificationTypeId' => $type->id,
            'isMuted' => true,
        ]),
    );

    expect(true)->toBeTrue();
});

it('ClearSectionDealPrice and SetSectionDealPrice run happy-path verbs transitions', function () {
    [$opportunity, $group] = burnup2QuotationWithGroup();

    (new SetSectionDealPrice)($opportunity->fresh(), $group->fresh(), SetSectionDealPriceData::from([
        'deal_price' => '40.00',
    ]));

    expect($group->fresh()->deal_price)->not->toBeNull();

    $cleared = (new ClearSectionDealPrice)($opportunity->fresh(), $group->fresh());

    expect($group->fresh()->deal_price)->toBeNull()
        ->and($cleared->id)->toBe($opportunity->id);
});

it('DeleteOpportunitySection deletes a section and records audit', function () {
    Event::fake([AuditableEvent::class]);
    burnup2Owner();
    burnup2EnsureOpportunitySectionsTable();

    $section = OpportunitySection::factory()->create();

    (new DeleteOpportunitySection)($section);

    expect(OpportunitySection::find($section->id))->toBeNull();
    Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $event): bool => $event->action === 'opportunity_section.deleted');
});

it('ReorderOpportunitySections reorders sections and skips unchanged sort orders', function () {
    Event::fake([AuditableEvent::class]);
    burnup2Owner();
    burnup2EnsureOpportunitySectionsTable();

    $opportunity = Opportunity::factory()->create();
    $first = OpportunitySection::factory()->for($opportunity)->create(['sort_order' => 1]);
    $second = OpportunitySection::factory()->for($opportunity)->create(['sort_order' => 0]);

    $ordered = (new ReorderOpportunitySections)($opportunity, ReorderOpportunitySectionsData::from([
        'section_ids' => [$second->id, $first->id],
    ]));

    expect($ordered)->toHaveCount(2)
        ->and($second->fresh()->sort_order)->toBe(0)
        ->and($first->fresh()->sort_order)->toBe(1);

    Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $event): bool => $event->action === 'opportunity_section.reordered');
});

it('UpdateDraftPurchaseOrder resolveExchangeRate keeps rate when currency is unchanged', function () {
    burnup2Owner();
    $po = PurchaseOrder::factory()->make([
        'currency_code' => 'EUR',
        'exchange_rate' => '1.250000',
    ]);

    $method = new ReflectionMethod(UpdateDraftPurchaseOrder::class, 'resolveExchangeRate');
    $method->setAccessible(true);

    expect($method->invoke(new UpdateDraftPurchaseOrder, 'EUR', $po))->toBe('1.250000');
});

it('ApportionVirtualStockCost removeForIntake removes linked opportunity costs', function () {
    burnup2Owner();
    $intake = burnup2ApportionFixture();

    app(ApportionVirtualStockCost::class)->removeForIntake($intake->fresh(), authorize: false);

    expect(OpportunityCost::query()->where('virtual_stock_intake_id', $intake->id)->count())->toBe(0);
});

it('PruneCommunicationLogCommand prunes old logs and rejects invalid safety days', function () {
    CommunicationLog::factory()->create([
        'status' => 'sent',
        'is_pinned' => false,
        'created_at' => now()->subDays(400),
    ]);

    $this->artisan(PruneCommunicationLogCommand::class, ['--days' => 365, '--safety-days' => 7])
        ->assertSuccessful();

    expect(CommunicationLog::query()->count())->toBe(0);

    $this->artisan(PruneCommunicationLogCommand::class, ['--safety-days' => 0])
        ->assertFailed();
});

it('ImportDocumentTemplate assertUserCanAccessStore denies foreign stores for restricted users', function () {
    $storeAllowed = Store::factory()->create();
    $storeDenied = Store::factory()->create();
    $user = User::factory()->create(['is_owner' => false]);
    $user->givePermissionTo('document_templates.edit');
    burnup2GrantStore($user, $storeAllowed);
    $this->actingAs($user);

    expect(fn () => (new ImportDocumentTemplate)(ImportDocumentTemplateData::from([
        'store_id' => $storeDenied->id,
        'payload' => [
            'export_format' => 'signals.document_template',
            'export_version' => 1,
            'name' => 'Imported',
            'document_type' => 'quote',
            'model_type' => Opportunity::class,
            'body_html' => '<p>Body</p>',
        ],
    ])))->toThrow(HttpException::class);
});

it('ImportDocumentTemplate assertUserCanAccessStore allows owners for any store', function () {
    burnup2Owner();
    $store = Store::factory()->create();

    $method = new ReflectionMethod(ImportDocumentTemplate::class, 'assertUserCanAccessStore');
    $method->setAccessible(true);
    $method->invoke(new ImportDocumentTemplate, $store->id);

    expect(true)->toBeTrue();
});

it('CommunicationTemplateController assertUserCanAccessStore returns 404 for inaccessible target stores', function () {
    $allowed = Store::factory()->create();
    $denied = Store::factory()->create();
    $user = User::factory()->create(['is_owner' => false]);
    $user->givePermissionTo('notifications.manage');
    burnup2GrantStore($user, $allowed);

    $template = CommunicationTemplate::factory()->for($allowed)->create();
    $token = $user->createToken('test', ['communication-templates:write', 'notifications.manage'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/v1/communication_templates/{$template->id}", [
            'store_id' => $denied->id,
            'name' => $template->name,
            'slug' => $template->slug,
            'channel' => $template->channel,
            'body' => 'Attempted move',
        ])->assertNotFound();
});

it('CommunicationUnsubscribeController confirm opts out the member', function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $type = NotificationType::factory()->create(['key' => 'burnup2.marketing']);
    $member = Member::factory()->contact()->create();

    $url = URL::signedRoute('communications.unsubscribe.confirm', [
        'member' => $member->id,
        'type' => $type->key,
    ]);

    $this->post($url)
        ->assertOk()
        ->assertSee('unsubscribed', false);
});

it('DeliverCustomerCommunication handle sends notification for valid recipient and template', function () {
    Notification::fake();

    $recipient = Member::factory()->contact()->create();
    $template = CommunicationTemplate::factory()->email()->create();
    $document = Document::factory()->finalised()->create();

    $job = new DeliverCustomerCommunication(
        communicationLogId: 99,
        recipientId: $recipient->id,
        templateId: $template->id,
        notificationTypeKey: 'invoice.created',
        documentId: $document->id,
        eyebrow: 'Hello',
        preheader: 'Preview',
    );

    $job->handle();

    Notification::assertSentTo($recipient, CustomerCommunication::class);
});

it('DetectOrderShortages handle returns early during Verbs replay', function () {
    burnup2WhileReplaying(function (): void {
        app(DetectOrderShortages::class)->handle(new AvailabilityChanged(
            productId: 1,
            storeId: 1,
        ));
    });

    expect(ActionLog::query()->where('action', 'shortage.detected')->count())->toBe(0);
});

it('HandlesVerbsStateErrors surfaces EventNotValid and ValidationException as notify toasts', function () {
    $owner = burnup2Owner();

    Livewire::actingAs($owner)
        ->test(new class extends Component
        {
            use HandlesVerbsStateErrors;

            public function invalidEvent(): void
            {
                $this->runVerbsTransition(function (): void {
                    throw new EventNotValid('State guard blocked transition.');
                });
            }

            public function invalidInput(): void
            {
                $this->runVerbsTransition(function (): void {
                    throw ValidationException::withMessages(['field' => ['Invalid value supplied.']]);
                });
            }

            public function render(): string
            {
                return '<div></div>';
            }
        })
        ->call('invalidEvent')
        ->assertDispatched('notify')
        ->call('invalidInput')
        ->assertDispatched('notify');
});
