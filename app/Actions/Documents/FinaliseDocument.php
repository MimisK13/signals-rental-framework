<?php

namespace App\Actions\Documents;

use App\Data\Documents\DocumentData;
use App\Enums\DocumentStatus;
use App\Events\AuditableEvent;
use App\Models\Document;
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

        event(new AuditableEvent(
            $document,
            'document.finalised',
            ['status' => $oldStatus],
            ['status' => DocumentStatus::Final->value],
        ));

        return DocumentData::fromModel(
            $document->refresh(),
            includeTemplate: true,
            includePublicLink: true,
            includeDownloadUrl: true,
        );
    }
}
