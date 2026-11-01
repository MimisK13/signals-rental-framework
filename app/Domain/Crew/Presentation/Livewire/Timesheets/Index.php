<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Livewire\Timesheets;

use App\Domain\Crew\Models\CrewTimesheet;
use App\Domain\Crew\Presentation\Livewire\CrewComponent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * @property-read LengthAwarePaginator<int, CrewTimesheet> $timesheets
 */
#[Layout('components.layouts.app')]
#[Title('Crew timesheets')]
final class Index extends CrewComponent
{
    use WithPagination;

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', CrewTimesheet::class);
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<int, CrewTimesheet> */
    #[Computed]
    public function timesheets(): LengthAwarePaginator
    {
        Gate::authorize('viewAny', CrewTimesheet::class);
        $query = CrewTimesheet::with(['crewMember', 'project'])->orderByDesc('period_start');
        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        return $query->paginate(25);
    }

    public function render(): View
    {
        return view('crew::timesheets.index');
    }
}
