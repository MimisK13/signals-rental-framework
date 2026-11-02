<?php

declare(strict_types=1);

namespace App\Domain\Communications\Actions;

use App\Domain\Communications\Models\CommunicationTemplate;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class DeleteCommunicationTemplate
{
    public function __invoke(CommunicationTemplate $template): void
    {
        Gate::authorize('delete', $template);

        if ($template->is_system) {
            throw ValidationException::withMessages([
                'communication_template' => ['System templates cannot be deleted.'],
            ]);
        }

        $template->delete();
    }
}
