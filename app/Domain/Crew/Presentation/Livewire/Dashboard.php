<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Livewire;

use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Models\CrewShift;
use App\Domain\Crew\Models\CrewTimesheet;
use App\Support\Timezone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/**
 * @property-read Collection<int, CrewRequest> $requests
 * @property-read Collection<int, CrewShift> $calls
 * @property-read int $approvals
 */
#[Layout('components.layouts.app')]
#[Title('Crew')]
final class Dashboard extends CrewComponent
{
    public function mount(): void
    {
        Gate::authorize('viewAny', CrewMember::class);
    }

    /** @return Collection<int, CrewRequest> */
    #[Computed]
    public function requests(): Collection
    {
        Gate::authorize('viewAny', CrewRequest::class);

        return CrewRequest::with(['project', 'lines.role'])->whereIn('status', ['open', 'partially_filled'])->orderBy('starts_at')->limit(15)->get();
    }

    /** @return Collection<int, CrewShift> */
    #[Computed]
    public function calls(): Collection
    {
        Gate::authorize('viewAny', CrewShift::class);
        $from = app(Timezone::class)->toLocal(now())->startOfDay()->utc();

        return CrewShift::with(['crewMember', 'project'])->where('starts_at', '>=', $from)->where('starts_at', '<', $from->addDay())->whereIn('status', ['confirmed', 'in_progress'])->orderBy('starts_at')->limit(30)->get();
    }

    #[Computed]
    public function approvals(): int
    {
        Gate::authorize('viewAny', CrewTimesheet::class);

        return CrewTimesheet::where('status', 'submitted')->count();
    }

    public function render(): View
    {
        return view('crew::dashboard');
    }
}
