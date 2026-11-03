<?php

declare(strict_types=1);

namespace App\Domain\CustomViews\Actions;

use App\Domain\Administration\Models\User;
use App\Domain\CustomViews\Models\CustomView;
use App\Domain\CustomViews\Models\UserViewPreference;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SetDefaultCustomView
{
    public function __invoke(User $user, string $entityType, CustomView $view): UserViewPreference
    {
        Gate::forUser($user)->authorize('view', $view);

        if ($view->entity_type !== $entityType) {
            throw ValidationException::withMessages([
                'view' => 'The selected view does not belong to this entity type.',
            ]);
        }

        return UserViewPreference::query()->updateOrCreate(
            ['user_id' => $user->id, 'entity_type' => $entityType],
            ['custom_view_id' => $view->id],
        );
    }
}
