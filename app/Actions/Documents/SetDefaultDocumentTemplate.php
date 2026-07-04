<?php

namespace App\Actions\Documents;

use App\Data\Documents\DocumentTemplateData;
use App\Models\DocumentTemplate;
use App\Services\Documents\DocumentEventDispatcher;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SetDefaultDocumentTemplate
{
    public function __invoke(DocumentTemplate $template): DocumentTemplateData
    {
        Gate::authorize('update', $template);

        try {
            return DB::transaction(function () use ($template): DocumentTemplateData {
                DocumentTemplate::query()
                    ->where('store_id', $template->store_id)
                    ->where('document_type', $template->document_type)
                    ->whereKeyNot($template->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);

                $template->update(['is_default' => true]);

                app(DocumentEventDispatcher::class)->template($template, 'document_template.set_as_default');

                return DocumentTemplateData::fromModel($template->refresh());
            });
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'is_default' => ['Another template was set as default concurrently. Please try again.'],
            ]);
        }
    }
}
