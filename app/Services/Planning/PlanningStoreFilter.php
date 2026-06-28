<?php

namespace App\Services\Planning;

use App\Models\Store;
use Illuminate\Support\Collection;

/**
 * Store filter helpers for planning views (planner + availability by group).
 *
 * Empty store id lists mean all {@see Store::scopeInDefaultQueries()} stores.
 */
class PlanningStoreFilter
{
    /**
     * @param  list<int>  $storeIds
     * @return list<int>
     */
    public function resolvedStoreIds(array $storeIds): array
    {
        if ($storeIds !== []) {
            return array_values(array_unique(array_map(intval(...), $storeIds)));
        }

        return Store::query()
            ->inDefaultQueries()
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @return Collection<int, Store>
     */
    public function storesForFilter(): Collection
    {
        return Store::query()
            ->inDefaultQueries()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return list<string>
     */
    public function storeNameSuggestions(): array
    {
        return $this->storesForFilter()
            ->pluck('name')
            ->all();
    }

    /**
     * @param  list<int>  $storeIds
     * @return list<string>
     */
    public function selectedStoreTags(array $storeIds): array
    {
        if ($storeIds === []) {
            return [];
        }

        $namesById = $this->storesForFilter()->pluck('name', 'id');

        return collect($storeIds)
            ->map(static fn (int $id): ?string => $namesById->get($id))
            ->filter(static fn (?string $name): bool => $name !== null && $name !== '')
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $tagNames
     * @return list<int>
     */
    public function storeIdsFromTags(array $tagNames): array
    {
        $namesById = $this->storesForFilter()->pluck('id', 'name');

        return collect($tagNames)
            ->map(static fn (string $name): ?int => $namesById->get($name))
            ->filter(static fn (?int $id): bool => $id !== null)
            ->map(static fn (int $id): int => $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $storeIds
     */
    public function primaryStoreId(array $storeIds): int
    {
        $resolved = $this->resolvedStoreIds($storeIds);

        return $resolved[0] ?? 0;
    }
}
