<?php

namespace App\Actions\Documents;

use App\Models\Document;
use App\Services\Documents\DocumentEventDispatcher;

/**
 * Seam for the Notification Engine (P38) to fire {@code document.emailed}.
 *
 * When email delivery is implemented, call this action after persisting
 * {@see Document::$emailed_at} and {@see Document::$emailed_to}.
 */
final class RecordDocumentEmailDelivery
{
    public function __invoke(Document $document, string $recipientEmail): void
    {
        $document->update([
            'emailed_at' => now(),
            'emailed_to' => $recipientEmail,
        ]);

        app(DocumentEventDispatcher::class)->document(
            $document,
            'document.emailed',
            null,
            ['emailed_to' => $recipientEmail],
        );
    }
}
