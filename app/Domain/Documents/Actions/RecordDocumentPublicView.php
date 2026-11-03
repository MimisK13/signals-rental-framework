<?php

declare(strict_types=1);

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Services\DocumentEventDispatcher;
use App\Domain\Stores\Scopes\StoreScope;

class RecordDocumentPublicView
{
    public function __invoke(Document $document): void
    {
        if ($document->first_viewed_at !== null) {
            return;
        }

        $document->update(['first_viewed_at' => now()]);

        app(DocumentEventDispatcher::class)->document(
            $document,
            'document.public_link_accessed',
            metadata: ['first_view' => true],
        );
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
