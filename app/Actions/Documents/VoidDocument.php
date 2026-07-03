<?php

namespace App\Actions\Documents;

use App\Data\Documents\DocumentData;
use App\Enums\DocumentStatus;
use App\Events\AuditableEvent;
use App\Models\Document;
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

        event(new AuditableEvent(
            $document,
            'document.voided',
            ['status' => $oldStatus],
            ['status' => DocumentStatus::Void->value],
        ));

        return DocumentData::fromModel(
            $document->refresh(),
            includeTemplate: true,
            includePublicLink: true,
            includeDownloadUrl: true,
        );
    }
}
