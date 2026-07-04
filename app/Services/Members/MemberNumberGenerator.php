<?php

namespace App\Services\Members;

use App\Models\DocumentSequence;
use App\Models\Store;
use App\Services\Documents\DocumentNumberGenerator;
use App\Services\Documents\DocumentSequenceDefaults;
use DateTimeInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Allocates formatted member numbers from a single global sequence.
 *
 * Members are not store-scoped; numbering uses the default store's sequence row
 * so {@see DocumentNumberGenerator} can reuse per-store locking and formatting.
 */
class MemberNumberGenerator
{
    public const DOCUMENT_TYPE = 'member';

    public function __construct(
        private readonly DocumentNumberGenerator $documentNumberGenerator,
    ) {}

    public function next(?DateTimeInterface $at = null): string
    {
        return $this->documentNumberGenerator->next(
            $this->sequenceStoreId(),
            self::DOCUMENT_TYPE,
            $at,
        );
    }

    public function ensureSequence(): DocumentSequence
    {
        $storeId = $this->sequenceStoreId();

        $sequence = DocumentSequence::query()
            ->where('store_id', $storeId)
            ->where('document_type', self::DOCUMENT_TYPE)
            ->first();

        if ($sequence !== null) {
            return $sequence;
        }

        return DocumentSequence::query()->create(array_merge(
            [
                'store_id' => $storeId,
                'document_type' => self::DOCUMENT_TYPE,
            ],
            DocumentSequenceDefaults::attributes(self::DOCUMENT_TYPE),
        ));
    }

    public function nextOrCreate(?DateTimeInterface $at = null): string
    {
        try {
            return $this->next($at);
        } catch (ModelNotFoundException) {
            $this->ensureSequence();

            return $this->next($at);
        }
    }

    private function sequenceStoreId(): int
    {
        $defaultStoreId = Store::query()->where('is_default', true)->value('id');

        if (is_int($defaultStoreId)) {
            return $defaultStoreId;
        }

        return (int) Store::query()->orderBy('id')->value('id');
    }
}
