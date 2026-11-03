<?php

declare(strict_types=1);

namespace App\Domain\Numbering\Actions;

use App\Domain\Numbering\Data\DocumentSequenceData;
use App\Domain\Numbering\Data\UpdateDocumentSequenceData;
use App\Domain\Numbering\Models\DocumentSequence;
use App\Domain\Numbering\Services\DocumentSequenceNextNumberGuard;
use Illuminate\Support\Facades\Gate;

class UpdateDocumentSequence
{
    public function __invoke(DocumentSequence $sequence, UpdateDocumentSequenceData $data): DocumentSequenceData
    {
        Gate::authorize('update', $sequence);

        if ($data->next_number !== null || $data->padding !== null) {
            UpdateDocumentSequenceData::assertNextNumberFitsPadding(
                $data->next_number ?? $sequence->next_number,
                $data->padding ?? $sequence->padding,
            );
        }

        app(DocumentSequenceNextNumberGuard::class)->assertBackwardsSetSafe($sequence, $data);

        $attributes = array_filter([
            'prefix' => $data->prefix,
            'suffix' => $data->suffix,
            'include_year' => $data->include_year,
            'include_month' => $data->include_month,
            'next_number' => $data->next_number,
            'padding' => $data->padding,
            'reset_frequency' => $data->reset_frequency,
        ], fn (mixed $value): bool => $value !== null);

        if ($attributes !== []) {
            $sequence->update($attributes);
        }

        return DocumentSequenceData::fromModel($sequence->refresh());
    }
}
