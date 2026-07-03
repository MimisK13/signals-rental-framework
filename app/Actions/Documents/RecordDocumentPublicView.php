<?php

namespace App\Actions\Documents;

use App\Events\AuditableEvent;
use App\Models\Document;
use App\Models\Scopes\StoreScope;

class RecordDocumentPublicView
{
    public function __invoke(Document $document): void
    {
        if ($document->first_viewed_at !== null) {
            return;
        }

        $document->update(['first_viewed_at' => now()]);

        event(new AuditableEvent(
            $document,
            'document.public_link_accessed',
            metadata: ['first_view' => true],
        ));

        // TODO(P22): dispatch document.public_link_accessed domain signal + webhook fan-out.
    }

    public static function findByToken(string $token): ?Document
    {
        return StoreScope::withoutScoping(function () use ($token): ?Document {
            return Document::query()
                ->where('public_token', $token)
                ->first();
        });
    }
}
