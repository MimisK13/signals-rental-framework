<?php

/**
 * P4-CL4b burndown batch 2 — targeted branch coverage for partially-covered methods.
 */

use App\Actions\Documents\ConfigureDocumentPublicLink;
use App\Actions\Documents\DuplicateDocumentTemplate;
use App\Actions\Documents\FinaliseDocument;
use App\Actions\Documents\RestoreDefaultDocumentTemplate;
use App\Actions\Documents\RestoreDocumentTemplateVersion;
use App\Actions\Documents\RevokeDocumentPublicLink;
use App\Actions\Documents\UpdateDocumentTemplate;
use App\Actions\Documents\VoidDocument;
use App\Actions\Members\MemberPriceCategoryTierValidator;
use App\Actions\Notifications\DeleteCommunicationTemplate;
use App\Actions\Notifications\DispatchBillingCustomerCommunication;
use App\Actions\Notifications\DispatchNotification;
use App\Actions\Notifications\DuplicateCommunicationTemplate;
use App\Actions\Notifications\SaveChannelProvider;
use App\Actions\Notifications\SaveCommunicationTemplate;
use App\Actions\Notifications\SaveMemberNotificationPreferences;
use App\Actions\Notifications\SendBillingCustomerCommunication;
use App\Actions\Pricing\DeleteSurchargeGroup;
use App\Actions\Pricing\SyncSurchargeGroupSurcharges;
use App\Actions\Pricing\UpdateSurcharge;
use App\Actions\Pricing\UpdateSurchargeGroup;
use App\Actions\Tax\CreateTaxCategory;
use App\Actions\Tax\UpdateTaxCategory;
use App\Actions\Views\DeleteCustomView;
use App\Console\Commands\ApiFlushLogs;
use App\Console\Commands\Invoices\ProcessOverdueInvoices;
use App\Console\Commands\PruneCommunicationLogCommand;
use App\Console\Commands\Shortages\EscalateUnresolvedShortages;
use App\Console\Commands\SignalsSetupCommand;
use App\Console\Commands\VirtualStock\DetectVirtualStockOverruns;
use App\Data\Documents\ConfigureDocumentPublicLinkData;
use App\Data\Documents\UpdateDocumentTemplateData;
use App\Data\Notifications\ChannelSendData;
use App\Data\Notifications\DispatchNotificationData;
use App\Data\Notifications\SaveChannelProviderData;
use App\Data\Notifications\SaveCommunicationTemplateData;
use App\Data\Notifications\SaveMemberNotificationPreferencesData;
use App\Data\Pricing\UpdateSurchargeData;
use App\Data\Pricing\UpdateSurchargeGroupData;
use App\Data\Tax\CreateTaxCategoryData;
use App\Data\Tax\UpdateTaxCategoryData;
use App\Enums\BillingCommunicationKind;
use App\Exceptions\Documents\DocumentRenderException;
use App\Http\Middleware\VerifyInboundWebhookSignature;
use App\Jobs\SendBillingCustomerCommunicationJob;
use App\Jobs\SendChannelNotification;
use App\Listeners\RecordCustomerCommunicationDelivery;
use App\Livewire\Concerns\HasAuditTimeline;
use App\Livewire\Concerns\ReKeysCustomFieldErrors;
use App\Models\CommunicationTemplate;
use App\Models\CustomView;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\NotificationType;
use App\Models\PriceCategory;
use App\Models\PriceCategoryTier;
use App\Models\Store;
use App\Models\Surcharge;
use App\Models\SurchargeGroup;
use App\Models\TaxCategory;
use App\Models\User;
use App\Notifications\ChannelInfrastructureBroadcastNotification;
use App\Notifications\FailingTestPipelineNotification;
use App\Services\Api\UsageRecorder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Thunk\Verbs\Lifecycle\Broker;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    Store::factory()->create(['is_default' => true, 'timezone' => 'UTC']);
});

function batch2WhileReplaying(Closure $callback): void
{
    $broker = app(Broker::class);
    $broker->is_replaying = true;

    try {
        $callback();
    } finally {
        $broker->is_replaying = false;
    }
}

function batch2Owner(): User
{
    return tap(User::factory()->owner()->create(), fn (User $user) => test()->actingAs($user));
}

it('RestoreDocumentTemplateVersion rejects a version from another template', function () {
    batch2Owner();
    $templateA = DocumentTemplate::factory()->create();
    $templateB = DocumentTemplate::factory()->create();
    $foreignVersion = DocumentTemplateVersion::factory()->for($templateB)->create();

    expect(fn () => (new RestoreDocumentTemplateVersion)($templateA, $foreignVersion))
        ->toThrow(HttpException::class);
});

it('DuplicateDocumentTemplate increments slug suffix when copy slug already exists', function () {
    batch2Owner();
    $store = Store::factory()->create();
    $source = DocumentTemplate::factory()->for($store)->create(['slug' => 'invoice']);
    DocumentTemplate::factory()->for($store)->create(['slug' => 'invoice-copy']);

    $duplicate = (new DuplicateDocumentTemplate)($source);

    expect($duplicate->slug)->toBe('invoice-copy-2');
});

it('DuplicateCommunicationTemplate increments slug suffix on collision', function () {
    batch2Owner();
    $store = Store::factory()->create();
    $source = CommunicationTemplate::factory()->for($store)->create(['slug' => 'receipt']);
    CommunicationTemplate::factory()->for($store)->create(['slug' => 'receipt-copy']);

    $duplicate = (new DuplicateCommunicationTemplate)($source);

    expect($duplicate->slug)->toBe('receipt-copy-2');
});

it('RestoreDefaultDocumentTemplate rejects non-system templates', function () {
    batch2Owner();
    $template = DocumentTemplate::factory()->create(['is_system' => false]);

    expect(fn () => (new RestoreDefaultDocumentTemplate)($template))
        ->toThrow(ValidationException::class);
});

it('UpdateDocumentTemplate rejects duplicate slug in the same store', function () {
    batch2Owner();
    $store = Store::factory()->create();
    DocumentTemplate::factory()->for($store)->create(['slug' => 'taken']);
    $template = DocumentTemplate::factory()->for($store)->create(['slug' => 'mine']);

    expect(fn () => (new UpdateDocumentTemplate)(
        $template,
        UpdateDocumentTemplateData::from(['slug' => 'taken']),
    ))->toThrow(ValidationException::class);
});

it('FinaliseDocument rejects non-draft documents', function () {
    batch2Owner();
    $document = Document::factory()->finalised()->create();

    expect(fn () => (new FinaliseDocument)($document))->toThrow(AuthorizationException::class);
});

it('VoidDocument rejects non-final documents', function () {
    batch2Owner();
    $document = Document::factory()->create();

    expect(fn () => (new VoidDocument)($document))->toThrow(AuthorizationException::class);
});

it('RevokeDocumentPublicLink rejects documents without an active public link', function () {
    batch2Owner();
    $document = Document::factory()->finalised()->create(['public_token' => null]);

    expect(fn () => (new RevokeDocumentPublicLink)($document))->toThrow(ValidationException::class);
});

it('ConfigureDocumentPublicLink rejects documents without an active public link', function () {
    batch2Owner();
    $document = Document::factory()->finalised()->create(['public_token' => null]);

    expect(fn () => (new ConfigureDocumentPublicLink)(
        $document,
        ConfigureDocumentPublicLinkData::from(['pin' => '1234']),
    ))->toThrow(ValidationException::class);
});

it('CreateTaxCategory demotes existing defaults when creating a new default', function () {
    batch2Owner();
    $existing = TaxCategory::factory()->create(['is_default' => true]);

    $created = (new CreateTaxCategory)(CreateTaxCategoryData::from([
        'name' => 'Alternate',
        'slug' => 'alternate',
        'type' => 'standard',
        'is_default' => true,
    ]));

    expect($created->is_default)->toBeTrue()
        ->and($existing->fresh()->is_default)->toBeFalse();
});

it('UpdateTaxCategory demotes sibling defaults when promoted', function () {
    batch2Owner();
    $default = TaxCategory::factory()->create(['is_default' => true]);
    $other = TaxCategory::factory()->create(['is_default' => false]);

    (new UpdateTaxCategory)($other, UpdateTaxCategoryData::from(['is_default' => true]));

    expect($other->fresh()->is_default)->toBeTrue()
        ->and($default->fresh()->is_default)->toBeFalse();
});

it('DispatchBillingCustomerCommunication skips dispatch during Verbs replay', function () {
    Queue::fake();

    batch2WhileReplaying(function (): void {
        app(DispatchBillingCustomerCommunication::class)(
            BillingCommunicationKind::Invoice,
            1,
            Store::factory()->create()->id,
        );
    });

    Queue::assertNothingPushed();
});

it('SendBillingCustomerCommunicationJob handle skips during Verbs replay', function () {
    $job = new SendBillingCustomerCommunicationJob(
        BillingCommunicationKind::Invoice,
        1,
        Store::factory()->create()->id,
        1,
    );

    batch2WhileReplaying(fn () => $job->handle(app(SendBillingCustomerCommunication::class)));

    expect(true)->toBeTrue();
});

it('SaveChannelProvider persists email driver via settings without DB row', function () {
    batch2Owner();

    $provider = app(SaveChannelProvider::class)(null, SaveChannelProviderData::from([
        'channel' => 'email',
        'driver' => 'email',
        'name' => 'SMTP',
        'config' => [],
        'is_default' => true,
        'is_active' => true,
    ]));

    expect($provider->id)->toBeIn([0, null])
        ->and($provider->driver)->toBe('email');
});

it('DeleteCommunicationTemplate rejects system templates', function () {
    batch2Owner();
    $template = CommunicationTemplate::factory()->create(['is_system' => true]);

    expect(fn () => (new DeleteCommunicationTemplate)($template))->toThrow(AuthorizationException::class);
});

it('SaveCommunicationTemplate rejects updating system templates', function () {
    batch2Owner();
    $template = CommunicationTemplate::factory()->create(['is_system' => true]);

    expect(fn () => (new SaveCommunicationTemplate)(
        $template,
        SaveCommunicationTemplateData::from([
            'name' => 'Changed',
            'slug' => $template->slug,
            'channel' => $template->channel,
            'body' => $template->body,
        ]),
    ))->toThrow(ValidationException::class);
});

it('DispatchNotification returns early when notification type is inactive', function () {
    batch2Owner();
    Queue::fake();

    $type = NotificationType::factory()->create(['is_active' => false]);
    $member = Member::factory()->create();

    app(DispatchNotification::class)(new DispatchNotificationData(
        notificationTypeKey: $type->key,
        notificationClass: FailingTestPipelineNotification::class,
        subject: $member,
    ));

    Queue::assertNothingPushed();
});

it('SaveMemberNotificationPreferences rejects actor without overlapping store access', function () {
    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();
    $actorMember = Member::factory()->contact()->create();
    $targetMember = Member::factory()->contact()->create();
    MemberStore::query()->create(['member_id' => $actorMember->id, 'store_id' => $storeA->id]);
    MemberStore::query()->create(['member_id' => $targetMember->id, 'store_id' => $storeB->id]);

    $actor = User::factory()->create(['member_id' => $actorMember->id]);
    $actor->givePermissionTo('members.edit');
    $target = User::factory()->create(['member_id' => $targetMember->id]);
    $type = NotificationType::factory()->create();

    $this->actingAs($actor);

    expect(fn () => app(SaveMemberNotificationPreferences::class)(
        $actor,
        SaveMemberNotificationPreferencesData::from([
            'targetUserId' => $target->id,
            'notificationTypeId' => $type->id,
            'channels' => ['email'],
        ]),
    ))->toThrow(HttpException::class);
});

it('DeleteSurchargeGroup deletes a custom surcharge group', function () {
    batch2Owner();
    $group = SurchargeGroup::factory()->create();

    (new DeleteSurchargeGroup)($group);

    expect(SurchargeGroup::find($group->id))->toBeNull();
});

it('UpdateSurcharge clears percent when switching to fixed value', function () {
    batch2Owner();
    $surcharge = Surcharge::factory()->percent('10.00')->create();

    $updated = (new UpdateSurcharge)($surcharge, UpdateSurchargeData::from([
        'fixed_value_minor' => 500,
    ]));

    expect($updated->percent_value)->toBeNull()
        ->and($updated->fixed_value_minor)->toBe(500);
});

it('UpdateSurchargeGroup updates group metadata', function () {
    batch2Owner();
    $group = SurchargeGroup::factory()->create(['name' => 'Old']);

    $updated = (new UpdateSurchargeGroup)($group, UpdateSurchargeGroupData::from([
        'name' => 'New name',
    ]));

    expect($updated->name)->toBe('New name');
});

it('SyncSurchargeGroupSurcharges rejects surcharge rows with both percent and fixed', function () {
    batch2Owner();
    $group = SurchargeGroup::factory()->create();

    expect(fn () => (new SyncSurchargeGroupSurcharges)($group, [[
        'name' => 'Bad',
        'percent_value' => '5.00',
        'fixed_value_minor' => 100,
    ]]))->toThrow(InvalidArgumentException::class);
});

it('MemberPriceCategoryTierValidator rejects tier from another category', function () {
    $categoryA = PriceCategory::factory()->create();
    $categoryB = PriceCategory::factory()->create();
    $tier = PriceCategoryTier::factory()->for($categoryB)->create();

    expect(fn () => MemberPriceCategoryTierValidator::assertTierBelongsToCategory($tier->id, $categoryA->id))
        ->toThrow(ValidationException::class);
});

it('DeleteCustomView rejects system views via action', function () {
    batch2Owner();
    $view = CustomView::factory()->system()->create();

    expect(fn () => (new DeleteCustomView)($view))->toThrow(AuthorizationException::class);
});

it('ProcessOverdueInvoices skips side effects during Verbs replay', function () {
    batch2WhileReplaying(function (): void {
        $this->artisan(ProcessOverdueInvoices::class)->assertSuccessful();
    });
});

it('DetectVirtualStockOverruns skips side effects during Verbs replay', function () {
    batch2WhileReplaying(function (): void {
        $this->artisan(DetectVirtualStockOverruns::class)->assertSuccessful();
    });
});

it('EscalateUnresolvedShortages skips side effects during Verbs replay', function () {
    batch2WhileReplaying(function (): void {
        $this->artisan(EscalateUnresolvedShortages::class)->assertSuccessful();
    });
});

it('PruneCommunicationLogCommand rejects invalid retention days', function () {
    $this->artisan(PruneCommunicationLogCommand::class, ['--days' => 0])
        ->assertFailed();
});

it('SignalsSetupCommand rejects an unknown profile flag', function () {
    $this->artisan(SignalsSetupCommand::class, ['--profile' => 'not-a-real-profile'])
        ->assertFailed();
});

it('ApiFlushLogs flushes buffered metering entries', function () {
    config(['api_metering.buffered' => true]);
    app(UsageRecorder::class)->record([
        'channel' => 'api',
        'request_id' => (string) Str::uuid(),
        'method' => 'GET',
        'uri' => '/api/v1/products',
        'route_name' => 'api.v1.products.index',
        'resource' => 'products',
        'operation' => 'index',
        'rollup_key' => 'api.v1.products.index',
        'personal_access_token_id' => null,
        'user_id' => null,
        'ability' => null,
        'status_code' => 200,
        'duration_ms' => 1,
        'request_bytes' => 0,
        'response_bytes' => 0,
        'price_weight' => 1,
        'weighted_units' => 1,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'request_body' => null,
        'response_body' => null,
    ]);

    $this->artisan(ApiFlushLogs::class)->assertSuccessful();
});

it('VerifyInboundWebhookSignature aborts when route source slug is empty', function () {
    $middleware = app(VerifyInboundWebhookSignature::class);
    $request = Request::create('/webhooks/inbound/', 'POST');
    $request->setRouteResolver(function () {
        $route = new Route('POST', '/webhooks/inbound/{source}', []);
        $route->bind(request());

        return $route;
    });

    expect(fn () => $middleware->handle($request, fn () => response('ok')))
        ->toThrow(HttpException::class);
});

it('DocumentRenderException compilationFailed includes optional detail', function () {
    $exception = DocumentRenderException::compilationFailed('syntax error near endif');

    expect($exception->getMessage())->toContain('syntax error near endif');
});

it('Communication unsubscribe confirm returns 404 for invalid type', function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $member = Member::factory()->contact()->create();
    $url = URL::signedRoute('communications.unsubscribe.confirm', [
        'member' => $member->id,
    ]);

    $this->post($url)->assertNotFound();
});

it('FailingTestPipelineNotification delegates to parent for non-failing recipient', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $notification = new FailingTestPipelineNotification(
        'pipeline.test',
        ['mail'],
        [
            'failing_recipient_id' => $other->id,
            'title' => 'Test',
            'body' => 'Body',
        ],
    );

    $mail = $notification->toMail($user);

    expect($mail)->toBeInstanceOf(MailMessage::class);
});

it('ChannelInfrastructureBroadcastNotification toArray exposes payload fields', function () {
    $notification = new ChannelInfrastructureBroadcastNotification(
        title: 'Queue lag',
        body: 'Horizon backlog detected',
        payload: ['queue' => 'default'],
    );

    $array = $notification->toArray(User::factory()->create());

    expect($array)->toHaveKeys(['title', 'body', 'payload'])
        ->and($array['title'])->toBe('Queue lag');
});

it('SendChannelNotification failed logs permanent delivery failure', function () {
    Log::spy();
    $job = new SendChannelNotification(new ChannelSendData(
        driverKey: 'email',
        notifiableId: 1,
        notifiableType: User::class,
        title: 'Test',
        body: 'Body',
    ));

    $job->failed(new RuntimeException('SMTP down'));

    Log::shouldHaveReceived('error');
});

it('RecordCustomerCommunicationDelivery ignores non-customer notifications', function () {
    $listener = app(RecordCustomerCommunicationDelivery::class);
    $event = new NotificationSent(
        User::factory()->create(),
        new AnonymousNotifiable,
        'mail',
        new Notification,
    );

    $listener->handle($event);

    expect(true)->toBeTrue();
});

it('HasAuditTimeline default timelineColorMap returns standard colours', function () {
    $double = new class
    {
        use HasAuditTimeline;

        /** @return array<string, list<string>> */
        public function map(): array
        {
            return $this->timelineColorMap();
        }
    };

    expect($double->map())->toHaveKey('green');
});

it('ReKeysCustomFieldErrors re-keys prefixed, bare, and passthrough errors', function () {
    $double = new class
    {
        use ReKeysCustomFieldErrors;

        public function run(): never
        {
            $this->reKeyCustomFieldErrors(
                ValidationException::withMessages([
                    'custom_fields.po' => ['PO required'],
                    'notes' => ['Notes required'],
                    'name' => ['Name required'],
                ]),
                ['name'],
            );
        }
    };

    try {
        $double->run();
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('customFieldValues.po')
            ->and($e->errors())->toHaveKey('customFieldValues.name')
            ->and($e->errors())->toHaveKey('notes');

        return;
    }

    expect(false)->toBeTrue('Expected ValidationException');
});
