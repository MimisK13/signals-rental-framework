<?php

namespace App\Actions\Documents;

use App\Data\Documents\DocumentSequenceData;
use App\Data\Documents\UpdateDocumentSequenceData;
use App\Models\DocumentSequence;
use Illuminate\Support\Facades\Gate;

class UpdateDocumentSequence
{
    public function __invoke(DocumentSequence $sequence, UpdateDocumentSequenceData $data): DocumentSequenceData
    {
        Gate::authorize('update', $sequence);

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
