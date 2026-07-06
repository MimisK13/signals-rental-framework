<?php

namespace App\Actions\Notifications;

use App\Models\CommunicationTemplate;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteCommunicationTemplate
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
