<?php

namespace App\Livewire\Components\Concerns;

trait HasDataTableSelection
{
    /** @var array<int, int> */
    public array $selected = [];

    public bool $selectAll = false;

    public ?int $lastSelectedId = null;

    public function toggleSelectAll(): void
    {
        $this->selectAll = ! $this->selectAll;

        if ($this->selectAll) {
            $this->syncSelectAllSelection();

            return;
        }

        $this->clearSelection();
    }

    public function toggleSelected(int $id): void
    {
        if (in_array($id, $this->selected)) {
            $this->selected = array_values(array_diff($this->selected, [$id]));
            $this->lastSelectedId = null;
        } else {
            $this->selected[] = $id;
            $this->lastSelectedId = $id;
        }

        $this->selectAll = false;
    }

    /**
     * Select all rows between the last selected row and the given row.
     *
     * @param  int  $id  The shift-clicked row ID
     * @param  array<int, int>  $pageIds  All row IDs on the current page, in display order
     */
    public function shiftSelect(int $id, array $pageIds): void
    {
        if ($this->lastSelectedId === null) {
            $this->toggleSelected($id);

            return;
        }

        $lastIndex = array_search($this->lastSelectedId, $pageIds);
        $currentIndex = array_search($id, $pageIds);

        if ($lastIndex === false || $currentIndex === false) {
            $this->toggleSelected($id);

            return;
        }

        $start = min($lastIndex, $currentIndex);
        $end = max($lastIndex, $currentIndex);

        $rangeIds = array_slice($pageIds, $start, $end - $start + 1);

        foreach ($rangeIds as $rangeId) {
            if (! in_array($rangeId, $this->selected)) {
                $this->selected[] = $rangeId;
            }
        }

        $this->lastSelectedId = $id;
        $this->selectAll = false;
    }

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->selectAll = false;
        $this->lastSelectedId = null;
    }

    public function updatedPage(?int $page = null, ?string $pageName = null): void
    {
        $this->syncSelectAllSelection();
    }

    protected function syncSelectAllSelection(): void
    {
        if (! $this->selectAll) {
            return;
        }

        $this->selected = $this->buildQuery()
            ->forPage($this->getPage(), $this->perPage)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }
}
