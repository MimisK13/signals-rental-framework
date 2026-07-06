<?php

namespace App\Actions\Documents;

use App\Data\Documents\DocumentData;
use App\Models\Document;
use App\Services\Documents\DocumentEventDispatcher;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RevokeDocumentPublicLink
{
    public function __invoke(Document $document): DocumentData
    {
        Gate::authorize('share', $document);

        if ($document->public_token === null || $document->public_token === '') {
            throw ValidationException::withMessages([
                'public_link' => ['This document does not have an active public link.'],
            ]);
        }

        $document->update([
            'public_token' => null,
            'public_expires_at' => null,
            'public_pin_hash' => null,
        ]);

        app(DocumentEventDispatcher::class)->document($document, 'document.public_link_revoked');

        return DocumentData::fromModel(
            $document->refresh(),
            includeTemplate: true,
            includePublicLink: true,
            includeDownloadUrl: true,
        );
    }
}
