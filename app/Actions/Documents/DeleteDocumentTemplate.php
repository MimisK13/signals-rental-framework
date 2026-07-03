<?php

namespace App\Actions\Documents;

use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteDocumentTemplate
{
    public function __invoke(DocumentTemplate $template): void
    {
        Gate::authorize('delete', $template);

        if ($template->is_system) {
            throw ValidationException::withMessages([
                'document_template' => ['System templates cannot be deleted.'],
            ]);
        }

        $template->delete();
    }
}
