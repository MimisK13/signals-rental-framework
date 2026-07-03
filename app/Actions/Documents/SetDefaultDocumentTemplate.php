<?php

namespace App\Actions\Documents;

use App\Data\Documents\DocumentTemplateData;
use App\Models\DocumentTemplate;
use App\Services\Documents\DocumentEventDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SetDefaultDocumentTemplate
{
    public function __invoke(DocumentTemplate $template): DocumentTemplateData
    {
        Gate::authorize('update', $template);

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
    }
}
