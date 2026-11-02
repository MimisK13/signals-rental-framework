<?php

declare(strict_types=1);

namespace App\Domain\CustomViews\Actions;

use App\Domain\CustomViews\Data\CreateCustomViewData;
use App\Domain\CustomViews\Data\CustomViewData;
use App\Domain\CustomViews\Models\CustomView;
use App\Services\ColumnRegistryResolver;
use Illuminate\Support\Facades\Gate;

final class CreateCustomView
{
    public function __invoke(CreateCustomViewData $data): CustomViewData
    {
        Gate::authorize('create', CustomView::class);

        if ($data->visibility === 'shared') {
            Gate::authorize('settings.manage');
        }

        app(ColumnRegistryResolver::class)->validateColumns(
            $data->entity_type,
            $data->columns,
            $data->sort_column,
            auth()->user(),
        );

        $view = CustomView::create([
            'name' => $data->name,
            'description' => $data->description,
            'entity_type' => $data->entity_type,
            'visibility' => $data->visibility,
            'user_id' => $data->visibility === 'personal' ? auth()->id() : null,
            'is_default' => false,
            'columns' => $data->columns,
            'filters' => $data->filters,
            'sort_column' => $data->sort_column,
            'sort_direction' => $data->sort_direction,
            'per_page' => $data->per_page,
            'config' => [],
        ]);

        if ($data->visibility === 'shared' && ! empty($data->role_ids)) {
            $view->roles()->sync($data->role_ids);
        }

        return CustomViewData::fromModel($view);
    }
}
