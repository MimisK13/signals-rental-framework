<?php

namespace App\Exceptions\Documents;

use RuntimeException;

class DuplicateDocumentNumberException extends RuntimeException
{
    public static function forStoreAndType(int $storeId, string $documentType, string $number): self
    {
        return new self(
            "Document number [{$number}] already exists for store [{$storeId}] and type [{$documentType}].",
        );
    }
}
