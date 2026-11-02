<?php

declare(strict_types=1);

namespace App\Domain\CustomViews\Actions;

use App\Domain\CustomViews\Models\CustomView;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class DeleteCustomView
{
    public function __invoke(CustomView $view): void
    {
        Gate::authorize('delete', $view);

        if ($view->visibility === 'system') {
            throw ValidationException::withMessages([
                'view' => ['System default views cannot be deleted.'],
            ]);
        }

        $view->delete();
    }
}
