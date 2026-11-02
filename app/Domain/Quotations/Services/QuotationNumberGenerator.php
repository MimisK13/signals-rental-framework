<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Services;

use App\Domain\Documents\Models\DocumentSequence;
use App\Domain\Documents\Services\DocumentNumberGenerator;
use App\Domain\Documents\Services\DocumentSequenceDefaults;
use DateTimeInterface;

final readonly class QuotationNumberGenerator
{
    public const string DOCUMENT_TYPE = 'quote';

    public function __construct(
        private DocumentNumberGenerator $documentNumberGenerator,
    ) {}

    public function next(int $storeId, ?DateTimeInterface $at = null): string
    {
        return $this->documentNumberGenerator->next($storeId, self::DOCUMENT_TYPE, $at);
    }

    public function ensureSequence(int $storeId): DocumentSequence
    {
        return DocumentSequenceDefaults::ensureExists($storeId, self::DOCUMENT_TYPE);
    }
}
