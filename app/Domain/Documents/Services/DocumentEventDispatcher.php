<?php

declare(strict_types=1);

namespace App\Domain\Documents\Services;

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Documents\Data\DocumentData;
use App\Domain\Documents\Data\DocumentSequenceData;
use App\Domain\Documents\Data\DocumentTemplateData;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentSequence;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Webhooks\Services\WebhookService;
use Thunk\Verbs\Facades\Verbs;

/**
 * Bridges document-domain mutations onto the audit log and outbound webhook bus.
 *
 * Document models are intentionally NOT handled by
 * {@see App\Domain\Webhooks\Listeners\DispatchWebhookForAuditableEvent}; this dispatcher is
 * their sole webhook emitter, mirroring the Phase-2 member/product action pattern.
 */
class DocumentEventDispatcher
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    public function document(
        Document $document,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
    ): void {
        event(new AuditableEvent($document, $action, $oldValues, $newValues, metadata: $metadata));

        $payload = [
            'document' => DocumentData::fromModel($document)->toArray(),
        ];

        if ($metadata !== null) {
            $payload = array_merge($payload, $metadata);
        }

        $this->dispatchWebhook($action, $payload);
    }

    public function pdfGenerated(Document $document): void
    {
        $this->document(
            $document,
            'document.pdf_generated',
            metadata: ['output_format' => 'pdf'],
        );
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function template(
        DocumentTemplate $template,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        event(new AuditableEvent($template, $action, $oldValues, $newValues));

        $this->dispatchWebhook($action, [
            'document_template' => DocumentTemplateData::fromModel($template)->toArray(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function sequence(DocumentSequence $sequence, string $action, ?array $metadata = null): void
    {
        event(new AuditableEvent($sequence, $action, metadata: $metadata));

        $this->dispatchWebhook($action, [
            'document_sequence' => DocumentSequenceData::fromModel($sequence)->toArray(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchWebhook(string $action, array $payload): void
    {
        if (Verbs::isReplaying()) {
            return;
        }

        if (! in_array($action, WebhookService::EVENTS, true)) {
            return;
        }

        app(WebhookService::class)->dispatch($action, $payload);
    }
}
