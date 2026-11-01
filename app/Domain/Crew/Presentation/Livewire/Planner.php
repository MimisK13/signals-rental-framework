<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Livewire;

use App\Domain\Crew\Models\CrewRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * @property-read LengthAwarePaginator<int, CrewRequest> $requests
 */
#[Layout('components.layouts.app')]
#[Title('Crew planner')]
final class Planner extends CrewComponent
{
    use WithPagination;

    #[Url]
    public bool $shortagesOnly = true;

    public function mount(): void
    {
        Gate::authorize('viewAny', CrewRequest::class);
    }

    public function updatedShortagesOnly(): void
    {
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<int, CrewRequest> */
    #[Computed]
    public function requests(): LengthAwarePaginator
    {
        Gate::authorize('viewAny', CrewRequest::class);
        $query = CrewRequest::with(['project', 'lines.role'])->whereNotIn('status', ['draft', 'cancelled', 'closed'])->orderBy('starts_at');
        if ($this->shortagesOnly) {
            $query->whereHas('lines', function ($query): void {
                $query->whereColumn('filled_quantity', '<', 'quantity');
            });
        }
        if (Gate::allows('crew.assignments.view')) {
            $query->with('lines.assignments.crewMember');
        }

        return $query->paginate(20);
    }

    public function render(): View
    {
        return view('crew::planner');
    }
}
