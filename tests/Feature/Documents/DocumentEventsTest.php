<?php

use App\Actions\Documents\ConfigureDocumentPublicLink;
use App\Actions\Documents\CreateDocumentPublicLink;
use App\Actions\Documents\FinaliseDocument;
use App\Actions\Documents\GenerateDocument;
use App\Actions\Documents\RecordDocumentPublicView;
use App\Actions\Documents\RevokeDocumentPublicLink;
use App\Actions\Documents\VoidDocument;
use App\Data\Documents\ConfigureDocumentPublicLinkData;
use App\Data\Documents\CreateDocumentPublicLinkData;
use App\Data\Documents\GenerateDocumentData;
use App\Enums\DocumentOutputFormat;
use App\Enums\DocumentStatus;
use App\Enums\OpportunityState;
use App\Enums\PricingRoundingDirection;
use App\Events\AuditableEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Document;
use App\Models\DocumentSequence;
use App\Models\DocumentTemplate;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OrganisationTaxClass;
use App\Models\Product;
use App\Models\ProductTaxClass;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\User;
use App\Models\Webhook;
use App\Services\Api\WebhookService;
use App\Services\Opportunities\Hooks\WorkflowTriggerRegistry;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
    settings()->set('company.date_format_php', 'd/m/Y');
});

/**
 * @return array{store: Store, opportunity: Opportunity, template: DocumentTemplate}
 */
function documentEventFixture(): array
{
    $orgClass = OrganisationTaxClass::factory()->create();
    $productClass = ProductTaxClass::factory()->create(['is_default' => true]);
    $taxRate = TaxRate::factory()->create(['rate' => '20.0000', 'is_active' => true]);

    TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass->id,
        'tax_rate_id' => $taxRate->id,
        'is_active' => true,
    ]);

    $store = Store::factory()->create();
    $member = Member::factory()->organisation()->create(['sale_tax_class_id' => $orgClass->id]);
    $product = Product::factory()->sale()->create(['tax_class_id' => $productClass->id]);

    $opportunity = Opportunity::factory()->create([
        'store_id' => $store->id,
        'member_id' => $member->id,
        'subject' => 'Event Test Quote',
        'currency_code' => 'GBP',
        'state' => OpportunityState::Quotation,
        'starts_at' => Carbon::parse('2026-07-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-07-03T17:00:00Z'),
        'charge_total' => 10_000,
        'tax_total' => 2_000,
    ]);

    OpportunityItem::factory()->sale()->create([
        'opportunity_id' => $opportunity->id,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'name' => 'Line',
        'quantity' => '1',
        'unit_price' => 10_000,
        'total' => 10_000,
    ]);

    $template = DocumentTemplate::factory()->for($store)->create([
        'document_type' => 'quote',
        'model_type' => Opportunity::class,
        'body_html' => '<p>{{ $opportunity->subject }}</p>',
        'is_active' => true,
    ]);

    DocumentSequence::factory()->create([
        'store_id' => $store->id,
        'document_type' => 'quote',
        'prefix' => 'QUO-',
        'padding' => 4,
        'next_number' => 1,
    ]);

    return compact('store', 'opportunity', 'template');
}

describe('document lifecycle events', function () {
    it('fires document.created audit and webhook when a document is generated', function () {
        Event::fake([AuditableEvent::class]);
        Queue::fake();

        ['opportunity' => $opportunity, 'template' => $template] = documentEventFixture();

        Webhook::factory()->create(['events' => ['document.created'], 'is_active' => true]);

        $document = app(GenerateDocument::class)(GenerateDocumentData::from([
            'document_template_id' => $template->id,
            'documentable_type' => 'opportunity',
            'documentable_id' => $opportunity->id,
            'output_format' => DocumentOutputFormat::Preview->value,
        ]));

        Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $event): bool => $event->action === 'document.created'
            && $event->model instanceof Document
            && $event->model->id === $document->id);

        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'document.created');
    });

    it('fires document.pdf_generated only when a persisted PDF is generated', function () {
        Event::fake([AuditableEvent::class]);
        Queue::fake();

        ['opportunity' => $opportunity, 'template' => $template] = documentEventFixture();

        Webhook::factory()->create(['events' => ['document.pdf_generated'], 'is_active' => true]);

        app(GenerateDocument::class)(GenerateDocumentData::from([
            'document_template_id' => $template->id,
            'documentable_type' => 'opportunity',
            'documentable_id' => $opportunity->id,
            'output_format' => DocumentOutputFormat::Pdf->value,
        ]));

        Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $event): bool => $event->action === 'document.pdf_generated');
        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'document.pdf_generated'
            && ($job->payload['output_format'] ?? null) === 'pdf'
            && isset($job->payload['document']['id']));
    });

    it('does not fire document.pdf_generated for preview renders', function () {
        Event::fake([AuditableEvent::class]);
        Queue::fake();

        ['opportunity' => $opportunity, 'template' => $template] = documentEventFixture();

        app(GenerateDocument::class)(GenerateDocumentData::from([
            'document_template_id' => $template->id,
            'documentable_type' => 'opportunity',
            'documentable_id' => $opportunity->id,
            'output_format' => DocumentOutputFormat::Preview->value,
        ]));

        Event::assertNotDispatched(AuditableEvent::class, fn (AuditableEvent $event): bool => $event->action === 'document.pdf_generated');
        Queue::assertNotPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'document.pdf_generated');
    });

    it('registers document.pdf_generated in the webhook allow-list', function () {
        expect(WebhookService::EVENTS)->toContain('document.pdf_generated');
    });

    it('fires document.finalised audit and webhook', function () {
        Event::fake([AuditableEvent::class]);
        Queue::fake();

        ['opportunity' => $opportunity, 'template' => $template] = documentEventFixture();
        Webhook::factory()->create(['events' => ['document.finalised'], 'is_active' => true]);

        $document = Document::factory()->for($template, 'documentTemplate')->create([
            'store_id' => $template->store_id,
            'documentable_type' => Opportunity::class,
            'documentable_id' => $opportunity->id,
            'status' => DocumentStatus::Draft,
        ]);

        (new FinaliseDocument)($document);

        Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $e): bool => $e->action === 'document.finalised');
        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'document.finalised');
    });

    it('fires document.voided audit and webhook', function () {
        Event::fake([AuditableEvent::class]);
        Queue::fake();

        ['opportunity' => $opportunity, 'template' => $template] = documentEventFixture();
        Webhook::factory()->create(['events' => ['document.voided'], 'is_active' => true]);

        $document = Document::factory()->for($template, 'documentTemplate')->create([
            'store_id' => $template->store_id,
            'documentable_type' => Opportunity::class,
            'documentable_id' => $opportunity->id,
            'status' => DocumentStatus::Final,
            'public_token' => 'abc123',
        ]);

        (new VoidDocument)($document);

        Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $e): bool => $e->action === 'document.voided');
        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'document.voided');
    });

    it('fires document.public_link_created and document.public_link_revoked webhooks', function () {
        Event::fake([AuditableEvent::class]);
        Queue::fake();

        ['opportunity' => $opportunity, 'template' => $template] = documentEventFixture();
        Webhook::factory()->create(['events' => ['document.public_link_created', 'document.public_link_revoked'], 'is_active' => true]);

        $document = Document::factory()->for($template, 'documentTemplate')->create([
            'store_id' => $template->store_id,
            'documentable_type' => Opportunity::class,
            'documentable_id' => $opportunity->id,
            'status' => DocumentStatus::Final,
        ]);

        app(CreateDocumentPublicLink::class)($document, new CreateDocumentPublicLinkData);
        (new RevokeDocumentPublicLink)($document->fresh());

        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'document.public_link_created');
        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'document.public_link_revoked');
    });

    it('fires document.public_link_accessed only on the first view', function () {
        Event::fake([AuditableEvent::class]);
        Queue::fake();

        ['opportunity' => $opportunity, 'template' => $template] = documentEventFixture();
        Webhook::factory()->create(['events' => ['document.public_link_accessed'], 'is_active' => true]);

        $document = Document::factory()->for($template, 'documentTemplate')->create([
            'store_id' => $template->store_id,
            'documentable_type' => Opportunity::class,
            'documentable_id' => $opportunity->id,
            'public_token' => 'view-token',
        ]);

        (new RecordDocumentPublicView)($document);
        (new RecordDocumentPublicView)($document->fresh());

        Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $e): bool => $e->action === 'document.public_link_accessed');
        Event::assertDispatchedTimes(AuditableEvent::class, 1);
        Queue::assertPushed(DeliverWebhook::class, 1);
    });

    it('registers document.emailed as a webhook event without auto-dispatch on generation', function () {
        expect(WebhookService::EVENTS)->toContain('document.emailed');

        Event::fake([AuditableEvent::class]);
        Queue::fake();

        ['opportunity' => $opportunity, 'template' => $template] = documentEventFixture();

        app(GenerateDocument::class)(GenerateDocumentData::from([
            'document_template_id' => $template->id,
            'documentable_type' => 'opportunity',
            'documentable_id' => $opportunity->id,
            'output_format' => DocumentOutputFormat::Preview->value,
        ]));

        Event::assertNotDispatched(AuditableEvent::class, fn (AuditableEvent $e): bool => $e->action === 'document.emailed');
        Queue::assertNotPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'document.emailed');
    });

    it('fires document.public_link_configured audit event', function () {
        Event::fake([AuditableEvent::class]);

        ['opportunity' => $opportunity, 'template' => $template] = documentEventFixture();

        $document = Document::factory()->for($template, 'documentTemplate')->create([
            'store_id' => $template->store_id,
            'documentable_type' => Opportunity::class,
            'documentable_id' => $opportunity->id,
            'public_token' => 'configured-token',
        ]);

        (new ConfigureDocumentPublicLink)($document, ConfigureDocumentPublicLinkData::from([
            'clear_pin' => true,
        ]));

        Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $e): bool => $e->action === 'document.public_link_configured');
    });
});

describe('document workflow triggers', function () {
    it('registers core document lifecycle triggers in the workflow registry', function () {
        $registry = app(WorkflowTriggerRegistry::class);

        expect($registry->has('document.created'))->toBeTrue()
            ->and($registry->has('document.finalised'))->toBeTrue()
            ->and($registry->has('document.public_link_accessed'))->toBeTrue()
            ->and($registry->forTransition('document.created'))->toContain('core-document-engine-seam');
    });
});

describe('document audit log integration', function () {
    it('writes action_logs rows for document.created end-to-end', function () {
        ['opportunity' => $opportunity, 'template' => $template] = documentEventFixture();
        $user = auth()->user();

        $document = app(GenerateDocument::class)(GenerateDocumentData::from([
            'document_template_id' => $template->id,
            'documentable_type' => 'opportunity',
            'documentable_id' => $opportunity->id,
            'output_format' => DocumentOutputFormat::Preview->value,
        ]));

        assertActionLogged('document.created', Document::class, $document->id, $user->id);
    });
});
