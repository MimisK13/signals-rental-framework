<?php

namespace App\Actions\Documents;

use App\Data\Documents\ConfigureDocumentPublicLinkData;
use App\Data\Documents\DocumentData;
use App\Models\Document;
use App\Services\Documents\DocumentEventDispatcher;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Optional;

class ConfigureDocumentPublicLink
{
    public function __invoke(Document $document, ConfigureDocumentPublicLinkData $data): DocumentData
    {
        Gate::authorize('share', $document);

        if ($document->public_token === null || $document->public_token === '') {
            throw ValidationException::withMessages([
                'public_link' => ['This document does not have an active public link.'],
            ]);
        }

        $updates = [];

        if (! $data->expires_at instanceof Optional) {
            $updates['public_expires_at'] = $data->expires_at;
        }

        if ($data->clear_pin) {
            $updates['public_pin_hash'] = null;
        } elseif (! $data->pin instanceof Optional && $data->pin !== null && $data->pin !== '') {
            $updates['public_pin_hash'] = Hash::make($data->pin);
        }

        if ($updates !== []) {
            $document->update($updates);
        }

        app(DocumentEventDispatcher::class)->document(
            $document,
            'document.public_link_configured',
            null,
            [
                'public_expires_at' => $document->public_expires_at?->toIso8601String(),
                'is_pin_protected' => is_string($document->public_pin_hash) && $document->public_pin_hash !== '',
            ],
        );

        return DocumentData::fromModel(
            $document->refresh(),
            includeTemplate: true,
            includePublicLink: true,
            includeDownloadUrl: true,
        );
    }
}
