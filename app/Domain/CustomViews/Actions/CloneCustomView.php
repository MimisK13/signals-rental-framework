<?php

declare(strict_types=1);

namespace App\Domain\CustomViews\Actions;

use App\Domain\CustomViews\Data\CustomViewData;
use App\Domain\CustomViews\Models\CustomView;
use Illuminate\Support\Facades\Gate;

final class CloneCustomView
{
    public function __invoke(CustomView $source): CustomViewData
    {
        Gate::authorize('view', $source);
        Gate::authorize('create', CustomView::class);

        $clone = CustomView::create([
            'name' => $source->name.' (Copy)',
            'entity_type' => $source->entity_type,
            'visibility' => 'personal',
            'user_id' => auth()->id(),
            'is_default' => false,
            'columns' => $source->columns,
            'filters' => $source->filters,
            'sort_column' => $source->sort_column,
            'sort_direction' => $source->sort_direction,
            'per_page' => $source->per_page,
            'config' => $source->config,
        ]);

        return CustomViewData::fromModel($clone);
    }
}
