<?php

namespace App\Livewire\Components;

use App\Livewire\Components\Concerns\HasDataTableColumns;
use App\Livewire\Components\Concerns\HasDataTableSelection;
use App\Livewire\Components\Concerns\HasDataTableViews;
use App\Services\DataTable\DataTableCsvExporter;
use App\Services\DataTable\DataTableQueryBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @property array<int, array<string, mixed>> $displayColumns
 * @property list<array{key: string, label: string, visible: bool}> $toggleableColumns
 */
class DataTable extends Component
{
    use HasDataTableColumns;
    use HasDataTableSelection;
    use HasDataTableViews;
    use WithPagination;

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $columns = [];

    #[Locked]
    public string $model = '';

    /** @var array<int, string> */
    #[Locked]
    public array $searchable = [];

    #[Url(as: 'per_page')]
    public int $perPage = 12;

    /** @var array<int, int> */
    #[Locked]
    public array $perPageOptions = [12, 24, 48];

    #[Locked]
    public string $emptyMessage = 'No records found.';

    #[Locked]
    public string $defaultSort = '';

    #[Locked]
    public string $defaultDirection = 'asc';

    /** @var array<int, string> */
    #[Locked]
    public array $with = [];

    /** @var array<int, string> */
    #[Locked]
    public array $withCounts = [];

    /** @var array<string, mixed> */
    #[Locked]
    public array $scopes = [];

    #[Locked]
    public string $actionsView = '';

    #[Locked]
    public string $bulkActionsView = '';

    #[Locked]
    public string $toolbarView = '';

    /** @var array<int, string> */
    #[Locked]
    public array $refreshEvents = [];

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortField = '';

    #[Url]
    public string $sortDirection = 'asc';

    /** @var array<string, string> */
    #[Url]
    public array $filters = [];

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<int, string>  $searchable
     * @param  array<int, string>  $with
     * @param  array<int, string>  $withCounts
     * @param  array<string, mixed>  $scopes
     * @param  array<int, string>  $refreshEvents
     */
    public function mount(
        array $columns = [],
        string $model = '',
        array $searchable = [],
        int $perPage = 12,
        string $emptyMessage = 'No records found.',
        string $defaultSort = '',
        string $defaultDirection = 'asc',
        array $with = [],
        array $withCounts = [],
        array $scopes = [],
        array $refreshEvents = [],
        ?string $entityType = null,
    ): void {
        if ($model === '' || ! class_exists($model) || ! is_subclass_of($model, Model::class)) {
            throw new \InvalidArgumentException('DataTable requires a valid Eloquent model class.');
        }

        if (str_starts_with($defaultSort, '-')) {
            $defaultSort = mb_substr($defaultSort, 1);
            $defaultDirection = 'desc';
        }

        $this->columns = $columns;
        $this->model = $model;
        $this->searchable = $searchable;
        $this->emptyMessage = $emptyMessage;
        $this->defaultSort = $defaultSort;
        $this->defaultDirection = $defaultDirection;
        $this->with = $with;
        $this->withCounts = $withCounts;
        $this->scopes = $scopes;
        $this->refreshEvents = $refreshEvents;

        if (! in_array($this->perPage, $this->perPageOptions, true)) {
            $this->perPage = $perPage;
        }

        if ($this->sortField === '' && $this->defaultSort !== '') {
            $this->sortField = $this->defaultSort;
            $this->sortDirection = $this->defaultDirection;
        }

        $this->entityType = $entityType;

        if ($this->entityType !== null) {
            $this->loadAvailableViews();
            $this->applyActiveView();
        }
    }

    /**
     * Register configurable event listeners for refresh.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $listeners = [];

        foreach ($this->refreshEvents as $event) {
            $listeners[$event] = 'refresh';
        }

        return $listeners;
    }

    public function sortBy(string $field): void
    {
        if (! $this->isSortableColumn($field)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
        $this->syncSelectAllSelection();
    }

    public function applyFilter(string $column, string $value): void
    {
        if (! $this->isFilterableColumn($column)) {
            return;
        }

        if ($value === '') {
            unset($this->filters[$column]);
        } else {
            $this->filters[$column] = $value;
        }

        $this->resetPage();
        $this->syncSelectAllSelection();
    }

    public function clearFilter(string $column): void
    {
        unset($this->filters[$column]);
        $this->resetPage();
        $this->syncSelectAllSelection();
    }

    public function clearAllFilters(): void
    {
        $this->filters = [];
        $this->search = '';
        $this->resetPage();
        $this->syncSelectAllSelection();
    }

    public function refresh(): void
    {
        $this->clearSelection();
    }

    public function setPerPage(int $perPage): void
    {
        if (! in_array($perPage, $this->perPageOptions)) {
            return;
        }

        $this->perPage = $perPage;
        $this->resetPage();
        $this->syncSelectAllSelection();
    }

    public function updatedSearch(): void
    {
        $this->search = mb_substr($this->search, 0, 200);
        $this->resetPage();
        $this->syncSelectAllSelection();
    }

    public function updatedFilters(): void
    {
        $this->resetPage();
        $this->syncSelectAllSelection();
    }

    /**
     * @return Builder<Model>
     */
    protected function buildQuery(): Builder
    {
        return app(DataTableQueryBuilder::class)->build(
            modelClass: $this->model,
            with: $this->with,
            withCounts: $this->withCounts,
            scopes: $this->scopes,
            columns: $this->columns,
            searchable: $this->searchable,
            filters: $this->filters,
            search: $this->search,
            sortField: $this->sortField,
            sortDirection: $this->sortDirection,
            viewId: $this->viewId,
            entityType: $this->entityType,
        );
    }

    /**
     * Export current query results as CSV using visible columns.
     */
    public function exportCsv(): StreamedResponse
    {
        $filename = ($this->entityType ?? 'export').'-'.now()->format('Y-m-d').'.csv';

        return app(DataTableCsvExporter::class)->export(
            query: $this->buildQuery(),
            columns: $this->displayColumns,
            filename: $filename,
        );
    }

    public function render(): View
    {
        $query = $this->buildQuery();
        $items = $query->paginate($this->perPage);
        $activeFilterCount = count(array_filter($this->filters, fn (?string $v): bool => $v !== '' && $v !== null));

        return view('livewire.components.data-table', [
            'items' => $items,
            'activeFilterCount' => $activeFilterCount,
            'totalCount' => $items->total(),
            'displayColumns' => $this->displayColumns,
        ]);
    }
}
