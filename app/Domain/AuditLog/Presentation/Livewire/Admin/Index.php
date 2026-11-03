<?php

declare(strict_types=1);

namespace App\Domain\AuditLog\Presentation\Livewire\Admin;

use App\Domain\AuditLog\Jobs\ExportActionLog;
use App\Domain\AuditLog\Models\ActionLog;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')] #[Title('Action Log')] final class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $filterAction = '';

    #[Url]
    public string $filterEntityType = '';

    #[Url]
    public string $filterUser = '';

    #[Url]
    public string $filterDateFrom = '';

    #[Url]
    public string $filterDateTo = '';

    public ?int $expandedRow = null;

    public function updatedFilterAction(): void
    {
        $this->resetPage();
    }

    public function updatedFilterEntityType(): void
    {
        $this->resetPage();
    }

    public function updatedFilterUser(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDateTo(): void
    {
        $this->resetPage();
    }

    public function toggleRow(int $id): void
    {
        $this->expandedRow = $this->expandedRow === $id ? null : $id;
    }

    public function clearFilters(): void
    {
        $this->filterAction = '';
        $this->filterEntityType = '';
        $this->filterUser = '';
        $this->filterDateFrom = '';
        $this->filterDateTo = '';
        $this->resetPage();
    }

    public function exportCsv(): void
    {
        $filters = array_filter([
            'action' => $this->filterAction,
            'auditable_type' => $this->filterEntityType,
            'date_from' => $this->filterDateFrom,
            'date_to' => $this->filterDateTo,
        ]);

        $userId = auth()->id();
        abort_unless(is_int($userId), 403);

        ExportActionLog::dispatch($userId, $filters);
        $this->dispatch('export-started');
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $query = ActionLog::query()
            ->with('user')
            ->latest('created_at');

        if ($this->filterAction !== '') {
            $query->forAction($this->filterAction);
        }

        if ($this->filterEntityType !== '') {
            $query->where('auditable_type', $this->filterEntityType);
        }

        if ($this->filterUser !== '') {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$this->filterUser}%"));
        }

        if ($this->filterDateFrom !== '') {
            $query->where('created_at', '>=', $this->filterDateFrom);
        }

        if ($this->filterDateTo !== '') {
            $query->where('created_at', '<=', $this->filterDateTo.' 23:59:59');
        }

        return [
            'logs' => $query->paginate(25),
            'actions' => ActionLog::query()->distinct()->pluck('action')->sort()->values(),
            'entityTypes' => ActionLog::query()->distinct()->pluck('auditable_type')->sort()->values(),
        ];
    }

    public function render(): View
    {
        return view('audit-log::admin.index', $this->with());
    }
}
