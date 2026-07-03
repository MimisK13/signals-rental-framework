<?php

namespace App\Actions\Documents;

use App\Data\Documents\CreateDocumentPublicLinkData;
use App\Data\Documents\DocumentData;
use App\Events\AuditableEvent;
use App\Models\Document;
use App\Services\Documents\DocumentPublicTokenGenerator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class CreateDocumentPublicLink
{
    public function __construct(
        private readonly DocumentPublicTokenGenerator $tokenGenerator,
    ) {}

    public function __invoke(Document $document, CreateDocumentPublicLinkData $data): DocumentData
    {
        Gate::authorize('share', $document);

        $token = $this->tokenGenerator->generate();

        $document->update([
            'public_token' => $token,
            'public_expires_at' => $data->expires_at,
            'public_pin_hash' => $this->hashPin($data->pin),
        ]);

        event(new AuditableEvent(
            $document,
            'document.public_link_created',
            null,
            [
                'public_expires_at' => $data->expires_at,
                'is_pin_protected' => $data->pin !== null && $data->pin !== '',
            ],
        ));

        return DocumentData::fromModel(
            $document->refresh(),
            includeTemplate: true,
            includePublicLink: true,
            includeDownloadUrl: true,
        );
    }

    private function hashPin(?string $pin): ?string
    {
        if ($pin === null || $pin === '') {
            return null;
        }

        return Hash::make($pin);
    }
}
