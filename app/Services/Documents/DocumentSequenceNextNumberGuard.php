<?php

namespace App\Services\Documents;

use App\Data\Documents\UpdateDocumentSequenceData;
use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Prevents lowering a sequence counter when re-issued numbers would collide with
 * existing entity projection rows for the same store.
 */
class DocumentSequenceNextNumberGuard
{
    public function __construct(
        private readonly DocumentNumberGenerator $documentNumberGenerator,
    ) {}

    public function assertBackwardsSetSafe(DocumentSequence $sequence, UpdateDocumentSequenceData $data): void
    {
        if ($data->next_number === null || $data->next_number >= $sequence->next_number) {
            return;
        }

        $table = DocumentSequenceEntityProjection::projectionTableFor($sequence->document_type);

        if ($table === null) {
            return;
        }

        $mergedSequence = $this->mergedSequence($sequence, $data);

        for ($number = $data->next_number; $number < $sequence->next_number; $number++) {
            $formatted = $this->documentNumberGenerator->formatNumber($mergedSequence, $number);

            if ($this->numberExistsOnProjection($table, $sequence, $formatted)) {
                throw ValidationException::withMessages([
                    'next_number' => ["Lowering the next number would re-issue an existing number ({$formatted})."],
                ]);
            }
        }
    }

    private function numberExistsOnProjection(string $table, DocumentSequence $sequence, string $formatted): bool
    {
        $query = DB::table($table)
            ->where('store_id', $sequence->store_id)
            ->where('number', $formatted);

        $invoiceType = DocumentSequenceEntityProjection::invoiceTypeForDocumentSequenceType($sequence->document_type);

        if ($table === 'invoices' && $invoiceType !== null) {
            $query->where('type', $invoiceType);
        }

        return $query->exists();
    }

    private function mergedSequence(DocumentSequence $sequence, UpdateDocumentSequenceData $data): DocumentSequence
    {
        $merged = $sequence->replicate();

        foreach (['prefix', 'suffix', 'include_year', 'include_month', 'padding'] as $attribute) {
            $value = $data->{$attribute};

            if ($value !== null) {
                $merged->{$attribute} = $value;
            }
        }

        return $merged;
    }
}
