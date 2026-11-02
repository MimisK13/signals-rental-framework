<?php

declare(strict_types=1);

namespace App\Domain\CustomViews\Actions;

use App\Domain\CustomViews\Data\CustomViewData;
use App\Domain\CustomViews\Data\UpdateCustomViewData;
use App\Domain\CustomViews\Models\CustomView;
use App\Services\ColumnRegistryResolver;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\Optional;

final class UpdateCustomView
{
    public function __invoke(CustomView $view, UpdateCustomViewData $data): CustomViewData
    {
        Gate::authorize('update', $view);

        if (! $data->columns instanceof Optional || ! $data->sort_column instanceof Optional) {
            app(ColumnRegistryResolver::class)->validateColumns(
                $view->entity_type,
                $data->columns instanceof Optional ? $view->columns : $data->columns,
                $data->sort_column instanceof Optional ? $view->sort_column : $data->sort_column,
                auth()->user(),
            );
        }

        $attributes = $data->toArray();
        $roleIds = $attributes['role_ids'] ?? null;
        unset($attributes['role_ids']);

        $visibility = $attributes['visibility'] ?? $view->visibility;

        if ($visibility === 'shared') {
            Gate::authorize('settings.manage');
            $attributes['user_id'] = null;
        } else {
            $attributes['user_id'] = auth()->id();
        }

        $view->update($attributes);

        if ($visibility === 'personal') {
            $view->roles()->detach();
        } elseif (is_array($roleIds)) {
            $view->roles()->sync($roleIds);
        }

        return CustomViewData::fromModel($view->fresh());
    }
}
