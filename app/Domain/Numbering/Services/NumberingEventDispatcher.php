<?php

declare(strict_types=1);

namespace App\Domain\Numbering\Services;

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Numbering\Data\DocumentSequenceData;
use App\Domain\Numbering\Models\DocumentSequence;
use App\Domain\Webhooks\Services\WebhookService;
use Thunk\Verbs\Facades\Verbs;

final class NumberingEventDispatcher
{
    /** @param array<string, mixed>|null $metadata */
    public function sequence(DocumentSequence $sequence, string $action, ?array $metadata = null): void
    {
        event(new AuditableEvent($sequence, $action, metadata: $metadata));

        if (Verbs::isReplaying() || ! in_array($action, WebhookService::EVENTS, true)) {
            return;
        }

        app(WebhookService::class)->dispatch($action, [
            'document_sequence' => DocumentSequenceData::fromModel($sequence)->toArray(),
        ]);
    }
}
