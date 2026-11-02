<?php

declare(strict_types=1);

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Data\DocumentData;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Services\DocumentEventDispatcher;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class VoidDocument
{
    public function __invoke(Document $document): DocumentData
    {
        Gate::authorize('void', $document);

        if ($document->status !== DocumentStatus::Final) {
            throw ValidationException::withMessages([
                'status' => ['Only finalised documents can be voided.'],
            ]);
        }

        $oldStatus = $document->status->value;

        $document->update([
            'status' => DocumentStatus::Void,
            'public_token' => null,
            'public_expires_at' => null,
            'public_pin_hash' => null,
        ]);

        app(DocumentEventDispatcher::class)->document(
            $document,
            'document.voided',
            ['status' => $oldStatus],
            ['status' => DocumentStatus::Void->value],
        );

        return DocumentData::fromModel(
            $document->refresh(),
            includeTemplate: true,
            includePublicLink: true,
            includeDownloadUrl: true,
        );
    }
}
