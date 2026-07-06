<?php

namespace App\Services\VirtualStock;

use App\Models\DocumentSequence;
use App\Services\Documents\DocumentNumberGenerator;
use DateTimeInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Allocates formatted virtual stock intake numbers from per-store document sequences.
 */
class VirtualStockNumberGenerator
{
    public const DOCUMENT_TYPE = 'virtual_stock_intake';

    public function __construct(
        private readonly DocumentNumberGenerator $documentNumberGenerator,
    ) {}

    public function next(int $storeId, ?DateTimeInterface $at = null): string
    {
        return $this->documentNumberGenerator->next($storeId, self::DOCUMENT_TYPE, $at);
    }

    public function ensureSequence(int $storeId): DocumentSequence
    {
        $sequence = DocumentSequence::query()
            ->where('store_id', $storeId)
            ->where('document_type', self::DOCUMENT_TYPE)
            ->first();

        if ($sequence !== null) {
            return $sequence;
        }

        return DocumentSequence::query()->create([
            'store_id' => $storeId,
            'document_type' => self::DOCUMENT_TYPE,
            'prefix' => (string) settings('virtual_stock.fallback_number_prefix', config('virtual_stock.fallback_number_prefix', 'VS-')),
            'suffix' => '',
            'include_year' => true,
            'include_month' => false,
            'next_number' => 1,
            'padding' => 4,
            'reset_frequency' => 'never',
        ]);
    }

    public function nextOrCreate(int $storeId, ?DateTimeInterface $at = null): string
    {
        try {
            return $this->next($storeId, $at);
        } catch (ModelNotFoundException) {
            $this->ensureSequence($storeId);

            return $this->next($storeId, $at);
        }
    }
}
