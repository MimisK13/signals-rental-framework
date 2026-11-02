<?php

declare(strict_types=1);

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Services\DocumentEventDispatcher;
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

        app(DocumentEventDispatcher::class)->template($template, 'document_template.deleted');

        $template->delete();
    }
}
