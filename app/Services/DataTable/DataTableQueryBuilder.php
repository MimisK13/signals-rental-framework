<?php

namespace App\Services\DataTable;

use App\Models\CustomView;
use App\Services\Api\RansackFilter;
use App\Services\ViewResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DataTableQueryBuilder
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, string>  $with
     * @param  array<int, string>  $withCounts
     * @param  array<string, mixed>  $scopes
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<int, string>  $searchable
     * @param  array<string, string>  $filters
     * @return Builder<Model>
     */
    public function build(
        string $modelClass,
        array $with,
        array $withCounts,
        array $scopes,
        array $columns,
        array $searchable,
        array $filters,
        string $search,
        string $sortField,
        string $sortDirection,
        ?int $viewId,
        ?string $entityType,
    ): Builder {
        /** @var Builder<Model> $query */
        $query = $modelClass::query();

        if ($with !== []) {
            $query->with($with);
        }

        if ($withCounts !== []) {
            $query->withCount($withCounts);
        }

        foreach ($scopes as $scope => $value) {
            if (! method_exists($modelClass, 'scope'.ucfirst($scope))) {
                throw new \InvalidArgumentException("DataTable: scope '{$scope}' does not exist on model {$modelClass}.");
            }

            if ($value === true) {
                $query->{$scope}();
            } else {
                $query->{$scope}($value);
            }
        }

        if ($viewId !== null && $entityType !== null) {
            $activeView = CustomView::find($viewId);

            if ($activeView instanceof CustomView) {
                app(ViewResolver::class)->applyFilters($query, $activeView);
            }
        }

        $this->applySearch($query, $search, $searchable);
        $this->applyFilters($query, $columns, $filters);

        if ($sortField !== '' && $this->isSortableColumn($columns, $sortField)) {
            $direction = in_array(strtolower($sortDirection), ['asc', 'desc'], true)
                ? $sortDirection
                : 'asc';

            $query->orderBy($sortField, $direction);
        }

        return $query;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $searchable
     */
    private function applySearch(Builder $query, string $search, array $searchable): void
    {
        $search = mb_substr($search, 0, 200);

        if ($search === '' || $searchable === []) {
            return;
        }

        $escapedSearch = RansackFilter::escapeLike($search);

        $query->where(function (Builder $query) use ($escapedSearch, $searchable): void {
            foreach ($searchable as $column) {
                $query->orWhere($column, 'ilike', "%{$escapedSearch}%");
            }
        });
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<string, string>  $filters
     */
    private function applyFilters(Builder $query, array $columns, array $filters): void
    {
        $filterableColumns = collect($columns)
            ->filter(fn (array $column): bool => $column['filterable'] ?? false)
            ->map(fn (array $column): string => $column['key'] ?? '')
            ->values()
            ->all();

        foreach ($filters as $column => $value) {
            if ($value === '' || ! in_array($column, $filterableColumns, true)) {
                continue;
            }

            $definition = collect($columns)->firstWhere('key', $column) ?? [];

            if (($definition['filter_type'] ?? 'text') === 'select') {
                $query->where($column, '=', $value);
            } else {
                $query->where($column, 'ilike', '%'.RansackFilter::escapeLike($value).'%');
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     */
    private function isSortableColumn(array $columns, string $field): bool
    {
        return collect($columns)->contains(
            fn (array $column): bool => ($column['key'] ?? '') === $field && ($column['sortable'] ?? false),
        );
    }
}
