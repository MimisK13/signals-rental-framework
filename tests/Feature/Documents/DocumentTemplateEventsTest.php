<?php

use App\Actions\Documents\CreateDocumentTemplate;
use App\Actions\Documents\DeleteDocumentTemplate;
use App\Actions\Documents\DuplicateDocumentTemplate;
use App\Actions\Documents\SetDefaultDocumentTemplate;
use App\Actions\Documents\UpdateDocumentTemplate;
use App\Data\Documents\CreateDocumentTemplateData;
use App\Data\Documents\UpdateDocumentTemplateData;
use App\Events\AuditableEvent;
use App\Jobs\DeliverWebhook;
use App\Models\DocumentTemplate;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use App\Models\Webhook;
use App\Services\Api\WebhookService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

describe('document template lifecycle events', function () {
    it('registers all document_template.* webhook events', function () {
        expect(WebhookService::EVENTS)->toContain(
            'document_template.created',
            'document_template.updated',
            'document_template.deleted',
            'document_template.set_as_default',
        );
    });

    it('fires document_template.created when a template is created', function () {
        Event::fake([AuditableEvent::class]);
        Queue::fake();
        Webhook::factory()->create(['events' => ['document_template.created'], 'is_active' => true]);

        $store = Store::factory()->create();

        (new CreateDocumentTemplate)(CreateDocumentTemplateData::from([
            'store_id' => $store->id,
            'name' => 'Custom Quote',
            'slug' => 'custom-quote',
            'document_type' => 'quote',
            'body_html' => '<p>Body</p>',
        ]));

        Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $e): bool => $e->action === 'document_template.created');
        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'document_template.created');
    });

    it('fires document_template.updated when a template is saved', function () {
        Event::fake([AuditableEvent::class]);
        Queue::fake();
        Webhook::factory()->create(['events' => ['document_template.updated'], 'is_active' => true]);

        $template = DocumentTemplate::factory()->create([
            'body_html' => '<p>Old</p>',
            'model_type' => Opportunity::class,
        ]);

        (new UpdateDocumentTemplate)($template, UpdateDocumentTemplateData::from([
            'body_html' => '<p>New</p>',
        ]));

        Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $e): bool => $e->action === 'document_template.updated');
        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'document_template.updated');
    });

    it('fires document_template.deleted when a custom template is removed', function () {
        Event::fake([AuditableEvent::class]);
        Queue::fake();
        Webhook::factory()->create(['events' => ['document_template.deleted'], 'is_active' => true]);

        $template = DocumentTemplate::factory()->create([
            'is_system' => false,
            'model_type' => Opportunity::class,
        ]);

        (new DeleteDocumentTemplate)($template);

        Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $e): bool => $e->action === 'document_template.deleted');
        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'document_template.deleted');
    });

    it('fires document_template.set_as_default when default status changes', function () {
        Event::fake([AuditableEvent::class]);
        Queue::fake();
        Webhook::factory()->create(['events' => ['document_template.set_as_default'], 'is_active' => true]);

        $store = Store::factory()->create();
        $template = DocumentTemplate::factory()->for($store)->create([
            'document_type' => 'quote',
            'is_default' => false,
            'model_type' => Opportunity::class,
        ]);

        (new SetDefaultDocumentTemplate)($template);

        Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $e): bool => $e->action === 'document_template.set_as_default');
        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'document_template.set_as_default');
    });

    it('fires document_template.created when duplicating a template', function () {
        Event::fake([AuditableEvent::class]);

        $template = DocumentTemplate::factory()->create(['model_type' => Opportunity::class]);

        (new DuplicateDocumentTemplate)($template);

        Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $e): bool => $e->action === 'document_template.created');
    });
});
