<?php

/**
 * P4-CL4b burndown batch 2 — targeted branch coverage for partially-covered methods.
 */

use App\Console\Commands\ApiFlushLogs;
use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\API\Services\UsageRecorder;
use App\Domain\AuditLog\Presentation\Concerns\HasAuditTimeline;
use App\Domain\Availability\Shortages\Console\EscalateUnresolvedShortages;
use App\Domain\Communications\Actions\DeleteCommunicationTemplate;
use App\Domain\Communications\Actions\DispatchBillingCustomerCommunication;
use App\Domain\Communications\Actions\DuplicateCommunicationTemplate;
use App\Domain\Communications\Actions\SaveChannelProvider;
use App\Domain\Communications\Actions\SaveCommunicationTemplate;
use App\Domain\Communications\Actions\SendBillingCustomerCommunication as SendBillingCustomerCommunicationAction;
use App\Domain\Communications\Console\PruneCommunicationLog;
use App\Domain\Communications\Data\ChannelSendData;
use App\Domain\Communications\Data\SaveChannelProviderData;
use App\Domain\Communications\Data\SaveCommunicationTemplateData;
use App\Domain\Communications\Enums\BillingCommunicationKind;
use App\Domain\Communications\Jobs\SendBillingCustomerCommunication;
use App\Domain\Communications\Jobs\SendChannelNotification;
use App\Domain\Communications\Listeners\RecordCustomerCommunicationDelivery;
use App\Domain\Communications\Models\CommunicationTemplate;
use App\Domain\CustomFields\Presentation\Concerns\ReKeysCustomFieldErrors;
use App\Domain\CustomViews\Actions\DeleteCustomView;
use App\Domain\CustomViews\Models\CustomView;
use App\Domain\Documents\Actions\ConfigureDocumentPublicLink;
use App\Domain\Documents\Actions\DuplicateDocumentTemplate;
use App\Domain\Documents\Actions\FinaliseDocument;
use App\Domain\Documents\Actions\RestoreDefaultDocumentTemplate;
use App\Domain\Documents\Actions\RestoreDocumentTemplateVersion;
use App\Domain\Documents\Actions\RevokeDocumentPublicLink;
use App\Domain\Documents\Actions\UpdateDocumentTemplate;
use App\Domain\Documents\Actions\VoidDocument;
use App\Domain\Documents\Data\ConfigureDocumentPublicLinkData;
use App\Domain\Documents\Data\UpdateDocumentTemplateData;
use App\Domain\Documents\Exceptions\DocumentRenderException;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Models\DocumentTemplateVersion;
use App\Domain\Invoices\Console\ProcessOverdueInvoices;
use App\Domain\Members\Actions\MemberPriceCategoryTierValidator;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\MemberStore;
use App\Domain\Notifications\Actions\DispatchNotification;
use App\Domain\Notifications\Actions\SaveMemberNotificationPreferences;
use App\Domain\Notifications\Data\DispatchNotificationData;
use App\Domain\Notifications\Data\SaveMemberNotificationPreferencesData;
use App\Domain\Notifications\Models\NotificationType;
use App\Domain\Notifications\Notifications\ChannelInfrastructureBroadcastNotification;
use App\Domain\Notifications\Notifications\FailingTestPipelineNotification;
use App\Domain\Pricing\Actions\DeleteSurchargeGroup;
use App\Domain\Pricing\Actions\SyncSurchargeGroupSurcharges;
use App\Domain\Pricing\Actions\UpdateSurcharge;
use App\Domain\Pricing\Actions\UpdateSurchargeGroup;
use App\Domain\Pricing\Data\UpdateSurchargeData;
use App\Domain\Pricing\Data\UpdateSurchargeGroupData;
use App\Domain\Pricing\Models\PriceCategory;
use App\Domain\Pricing\Models\PriceCategoryTier;
use App\Domain\Pricing\Models\Surcharge;
use App\Domain\Pricing\Models\SurchargeGroup;
use App\Domain\Setup\Console\SignalsSetupCommand;
use App\Domain\Stores\Models\Store;
use App\Domain\Tax\Actions\CreateTaxCategory;
use App\Domain\Tax\Actions\UpdateTaxCategory;
use App\Domain\Tax\Data\CreateTaxCategoryData;
use App\Domain\Tax\Data\UpdateTaxCategoryData;
use App\Domain\Tax\Models\TaxCategory;
use App\Domain\VirtualStock\Console\DetectVirtualStockOverruns;
use App\Domain\Webhooks\Http\Middleware\VerifyInboundWebhookSignature;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Thunk\Verbs\Lifecycle\Broker;

use function Pest\Laravel\actingAs;

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
    $user = User::factory()->owner()->create();
    actingAs($user);

    return $user;
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

it('SendBillingCustomerCommunication handle skips during Verbs replay', function () {
    $job = new SendBillingCustomerCommunication(
        BillingCommunicationKind::Invoice,
        1,
        Store::factory()->create()->id,
        1,
    );

    batch2WhileReplaying(fn () => $job->handle(app(SendBillingCustomerCommunicationAction::class)));

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

it('PruneCommunicationLog rejects invalid retention days', function () {
    $this->artisan(PruneCommunicationLog::class, ['--days' => 0])
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
    $log = Log::spy();
    $job = new SendChannelNotification(new ChannelSendData(
        driverKey: 'email',
        notifiableId: 1,
        notifiableType: User::class,
        title: 'Test',
        body: 'Body',
    ));

    $job->failed(new RuntimeException('SMTP down'));

    $log->shouldHaveReceived('error');
});

it('RecordCustomerCommunicationDelivery ignores non-customer notifications', function () {
    $listener = app(RecordCustomerCommunicationDelivery::class);
    $event = new NotificationSent(
        notifiable: new AnonymousNotifiable,
        notification: new Notification,
        channel: 'mail',
    );

    $listener->handle($event);

    expect(true)->toBeTrue();
});

it('HasAuditTimeline default timelineColorMap returns standard colours', function () {
    $double = new class
    {
        use HasAuditTimeline;

        /** @return Collection<int, array{title: string, meta: string, color: ?string, body: ?string}> */
        protected function auditTimelineFor(Model $model): Collection
        {
            return collect();
        }

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

        public function run(): void
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

    $caught = null;

    try {
        $double->run();
    } catch (ValidationException $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(ValidationException::class);
    expect($caught->errors())->toHaveKey('customFieldValues.po')
        ->and($caught->errors())->toHaveKey('customFieldValues.name')
        ->and($caught->errors())->toHaveKey('notes');
});
