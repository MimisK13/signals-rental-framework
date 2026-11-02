<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Presentation\Livewire;

use App\Domain\Projects\Models\Project;
use App\Domain\Quotations\Enums\QuotationStatus;
use App\Domain\Quotations\Models\Quotation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Quotations')]
final class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public ?int $project = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Quotation::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<int, Quotation> */
    private function quotations(): LengthAwarePaginator
    {
        return Quotation::query()
            ->with(['customer:id,name', 'project:id,name', 'activeVersion:id,quotation_id,status'])
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('subject', 'like', '%'.$this->search.'%')
                        ->orWhere('number', 'like', '%'.$this->search.'%')
                        ->orWhere('reference', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->project !== null, fn ($query) => $query->where('project_id', $this->project))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25);
    }

    public function render(): View
    {
        return view('quotations::index', [
            'quotations' => $this->quotations(),
            'statuses' => QuotationStatus::cases(),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
