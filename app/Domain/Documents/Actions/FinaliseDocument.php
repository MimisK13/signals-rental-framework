<?php

declare(strict_types=1);

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Data\DocumentData;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Services\DocumentEventDispatcher;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class FinaliseDocument
{
    public function __invoke(Document $document): DocumentData
    {
        Gate::authorize('finalise', $document);

        if ($document->status !== DocumentStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => ['Only draft documents can be finalised.'],
            ]);
        }

        $oldStatus = $document->status->value;

        $document->update(['status' => DocumentStatus::Final]);

        app(DocumentEventDispatcher::class)->document(
            $document,
            'document.finalised',
            ['status' => $oldStatus],
            ['status' => DocumentStatus::Final->value],
        );

        return DocumentData::fromModel(
            $document->refresh(),
            includeTemplate: true,
            includePublicLink: true,
            includeDownloadUrl: true,
        );
    }
}
