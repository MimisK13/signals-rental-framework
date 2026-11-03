<?php

declare(strict_types=1);

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Exceptions\DuplicateDocumentNumberException;
use App\Domain\Documents\Models\Document;

final class DocumentNumberValidator
{
    public function assertUnique(int $storeId, string $documentType, string $number): void
    {
        $exists = Document::query()
            ->where('store_id', $storeId)
            ->where('document_type', $documentType)
            ->where('document_number', $number)
            ->exists();

        if ($exists) {
            throw DuplicateDocumentNumberException::forStoreAndType($storeId, $documentType, $number);
        }
    }
}
