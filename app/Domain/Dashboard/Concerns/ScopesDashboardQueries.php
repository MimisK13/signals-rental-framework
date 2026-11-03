<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Concerns;

use App\Domain\Administration\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait ScopesDashboardQueries
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function scopeToAccessibleStores(Builder $query, ?User $user, string $column = 'store_id'): Builder
    {
        $storeIds = $user?->accessibleStoreIds();

        if ($storeIds === null) {
            return $query;
        }

        if ($storeIds === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn($column, $storeIds);
    }
}
