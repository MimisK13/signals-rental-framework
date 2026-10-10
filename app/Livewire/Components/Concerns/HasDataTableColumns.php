<?php

namespace App\Livewire\Components\Concerns;

use App\Services\ColumnRegistryResolver;
use App\Views\ColumnRegistry;

trait HasDataTableColumns
{
    /** @var list<string> Column keys to display (empty = show all) */
    public array $visibleColumnKeys = [];

    /** @return array<string, mixed> */
    protected function getColumnDefinition(string $key): array
    {
        foreach ($this->columns as $column) {
            if (($column['key'] ?? '') === $key) {
                return $column;
            }
        }

        return [];
    }

    protected function isSortableColumn(string $field): bool
    {
        foreach ($this->columns as $column) {
            if (($column['key'] ?? '') === $field && ($column['sortable'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    protected function isFilterableColumn(string $column): bool
    {
        return in_array($column, $this->getFilterableColumnKeys(), true);
    }

    /** @return array<int, string> */
    protected function getFilterableColumnKeys(): array
    {
        return collect($this->columns)
            ->filter(fn (array $column): bool => $column['filterable'] ?? false)
            ->map(fn (array $column): string => $column['key'] ?? '')
            ->values()
            ->all();
    }

    public function toggleColumn(string $key): void
    {
        if (empty($this->visibleColumnKeys)) {
            $this->visibleColumnKeys = collect($this->columns)
                ->reject(fn (array $column): bool => in_array($column['type'] ?? null, ['checkbox', 'actions']) || ($column['key'] ?? '') === 'avatar')
                ->pluck('key')
                ->all();
        }

        if (in_array($key, $this->visibleColumnKeys, true)) {
            $this->visibleColumnKeys = array_values(array_filter(
                $this->visibleColumnKeys,
                fn (string $columnKey): bool => $columnKey !== $key,
            ));
        } else {
            $this->visibleColumnKeys[] = $key;
        }
    }

    /** @return list<array{key: string, label: string, visible: bool}> */
    public function getToggleableColumnsProperty(): array
    {
        $registry = $this->getColumnRegistry();
        $visibleKeys = $this->visibleColumnKeys;

        if (empty($visibleKeys)) {
            $visibleKeys = collect($this->columns)
                ->reject(fn (array $column): bool => in_array($column['type'] ?? null, ['checkbox', 'actions']) || ($column['key'] ?? '') === 'avatar')
                ->pluck('key')
                ->all();
        }

        if ($registry !== null) {
            $result = [];

            foreach ($registry->visibleColumns(auth()->user()) as $column) {
                $result[] = [
                    'key' => $column->key,
                    'label' => $column->label,
                    'visible' => in_array($column->key, $visibleKeys, true),
                ];
            }

            return $result;
        }

        return collect($this->columns)
            ->reject(fn (array $column): bool => in_array($column['type'] ?? null, ['checkbox', 'actions']) || ($column['key'] ?? '') === 'avatar')
            ->map(fn (array $column): array => [
                'key' => $column['key'] ?? '',
                'label' => $column['label'] ?? $column['key'] ?? '',
                'visible' => in_array($column['key'] ?? '', $visibleKeys, true),
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function getDisplayColumnsProperty(): array
    {
        if (empty($this->visibleColumnKeys)) {
            return $this->columns;
        }

        $existingByKey = [];
        $structural = [];

        foreach ($this->columns as $column) {
            $type = $column['type'] ?? null;
            $key = $column['key'] ?? '';

            if ($type === 'checkbox' || $type === 'actions') {
                $structural[$type] = $column;
            } elseif ($key === 'avatar') {
                $structural['avatar'] = $column;
            } else {
                $existingByKey[$key] = $column;
            }
        }

        $result = [];

        if (isset($structural['checkbox'])) {
            $result[] = $structural['checkbox'];
        }

        $avatarPairKey = $structural['avatar']['pairWith'] ?? 'name';
        if (isset($structural['avatar']) && in_array($avatarPairKey, $this->visibleColumnKeys, true)) {
            $result[] = $structural['avatar'];
        }

        $registry = $this->entityType !== null ? $this->getColumnRegistry() : null;

        foreach ($this->visibleColumnKeys as $key) {
            if (isset($existingByKey[$key])) {
                $result[] = $existingByKey[$key];
            } elseif ($registry !== null) {
                $registeredColumn = $registry->get($key);

                if ($registeredColumn !== null) {
                    $result[] = [
                        'key' => $registeredColumn->key,
                        'label' => $registeredColumn->label,
                        'sortable' => $registeredColumn->sortable,
                        'filterable' => $registeredColumn->filterable,
                    ];
                }
            }
        }

        if (isset($structural['actions'])) {
            $result[] = $structural['actions'];
        }

        return $result;
    }

    private function getColumnRegistry(): ?ColumnRegistry
    {
        if ($this->entityType === null) {
            return null;
        }

        return app(ColumnRegistryResolver::class)->resolve($this->entityType);
    }
}
