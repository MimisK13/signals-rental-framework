<?php

namespace App\Services\Documents;

use App\Enums\DocumentSequenceResetFrequency;
use App\Exceptions\Documents\DuplicateDocumentNumberException;
use App\Models\Document;
use App\Models\DocumentSequence;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Allocates formatted document numbers from per-store, per-type sequences.
 *
 * Uses {@see DocumentSequence} rows with SELECT FOR UPDATE inside a database
 * transaction so concurrent generation cannot produce duplicate numbers.
 * Manual overrides are validated for uniqueness but never advance the counter.
 */
class DocumentNumberGenerator
{
    /**
     * Allocate and format the next document number for a store and type.
     */
    public function next(int $storeId, string $documentType, ?DateTimeInterface $at = null): string
    {
        return DocumentSequence::query()->getConnection()->transaction(function () use ($storeId, $documentType, $at): string {
            $sequence = DocumentSequence::query()
                ->where('store_id', $storeId)
                ->where('document_type', $documentType)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                throw (new ModelNotFoundException)->setModel(DocumentSequence::class);
            }

            $instant = $this->resolveInstant($sequence, $at);
            $this->applyPeriodReset($sequence, $instant);

            $number = $sequence->next_number;
            $formatted = $this->format($sequence, $number, $instant);

            $sequence->next_number = $number + 1;
            $sequence->save();

            return $formatted;
        });
    }

    /**
     * Preview the next formatted number without consuming the sequence.
     */
    public function preview(int $storeId, string $documentType, ?DateTimeInterface $at = null): string
    {
        $sequence = DocumentSequence::query()
            ->where('store_id', $storeId)
            ->where('document_type', $documentType)
            ->firstOrFail();

        $instant = $this->resolveInstant($sequence, $at);
        $number = $this->nextNumberAfterPeriodCheck($sequence, $instant);

        return $this->format($sequence, $number, $instant);
    }

    /**
     * Ensure a manually assigned number is unique within the store and type.
     *
     * Manual numbers do not advance the automatic sequence counter.
     */
    public function validateManualNumber(int $storeId, string $documentType, string $number): void
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

    private function resolveInstant(DocumentSequence $sequence, ?DateTimeInterface $at): CarbonImmutable
    {
        $sequence->loadMissing('store');
        $timezone = $sequence->store->timezone ?? 'UTC';

        if ($at !== null) {
            return CarbonImmutable::instance($at)->setTimezone($timezone);
        }

        return CarbonImmutable::now($timezone);
    }

    private function applyPeriodReset(DocumentSequence $sequence, CarbonInterface $instant): void
    {
        $frequency = DocumentSequenceResetFrequency::tryFrom($sequence->reset_frequency)
            ?? DocumentSequenceResetFrequency::Never;

        if ($frequency === DocumentSequenceResetFrequency::Never) {
            return;
        }

        $year = (int) $instant->format('Y');
        $month = (int) $instant->format('n');
        $shouldReset = false;

        if ($frequency === DocumentSequenceResetFrequency::Yearly) {
            if ($sequence->current_period_year !== null && $sequence->current_period_year !== $year) {
                $sequence->next_number = 1;
                $shouldReset = true;
            }

            $sequence->current_period_year = $year;

            if ($shouldReset) {
                app(DocumentEventDispatcher::class)->sequence($sequence, 'document_sequence.reset', [
                    'reset_frequency' => $frequency->value,
                    'period_year' => $year,
                ]);
            }

            return;
        }

        $periodChanged = $sequence->current_period_year !== $year
            || $sequence->current_period_month !== $month;

        if ($periodChanged && $sequence->current_period_year !== null) {
            $sequence->next_number = 1;
            $shouldReset = true;
        }

        $sequence->current_period_year = $year;
        $sequence->current_period_month = $month;

        if ($shouldReset) {
            app(DocumentEventDispatcher::class)->sequence($sequence, 'document_sequence.reset', [
                'reset_frequency' => $frequency->value,
                'period_year' => $year,
                'period_month' => $month,
            ]);
        }
    }

    private function nextNumberAfterPeriodCheck(DocumentSequence $sequence, CarbonInterface $instant): int
    {
        $frequency = DocumentSequenceResetFrequency::tryFrom($sequence->reset_frequency)
            ?? DocumentSequenceResetFrequency::Never;

        if ($frequency === DocumentSequenceResetFrequency::Never) {
            return $sequence->next_number;
        }

        $year = (int) $instant->format('Y');
        $month = (int) $instant->format('n');

        if ($frequency === DocumentSequenceResetFrequency::Yearly) {
            if ($sequence->current_period_year !== null && $sequence->current_period_year !== $year) {
                return 1;
            }

            return $sequence->next_number;
        }

        $periodChanged = $sequence->current_period_year !== $year
            || $sequence->current_period_month !== $month;

        if ($periodChanged && $sequence->current_period_year !== null) {
            return 1;
        }

        return $sequence->next_number;
    }

    private function format(DocumentSequence $sequence, int $number, CarbonInterface $instant): string
    {
        $formatted = $sequence->prefix;

        $datePart = $this->dateSegment($sequence, $instant);

        if ($datePart !== '') {
            $formatted .= $datePart.'-';
        }

        $formatted .= str_pad((string) $number, $sequence->padding, '0', STR_PAD_LEFT);
        $formatted .= $sequence->suffix;

        return $formatted;
    }

    private function dateSegment(DocumentSequence $sequence, CarbonInterface $instant): string
    {
        if ($sequence->include_year && $sequence->include_month) {
            return $instant->format('Y-m');
        }

        if ($sequence->include_year) {
            return $instant->format('Y');
        }

        if ($sequence->include_month) {
            return $instant->format('m');
        }

        return '';
    }
}
