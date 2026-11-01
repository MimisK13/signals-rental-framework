<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Livewire;

use App\Domain\Crew\Models\CrewAvailability;
use App\Domain\Crew\Models\CrewShift;
use App\Support\Timezone;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * @property-read LengthAwarePaginator<int, CrewShift> $shifts
 * @property-read Collection<int, CrewAvailability> $availability
 */
#[Layout('components.layouts.app')]
#[Title('Crew calendar')]
final class Calendar extends CrewComponent
{
    use WithPagination;

    #[Url]
    public string $week = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', CrewShift::class);
        if ($this->week === '') {
            $this->week = app(Timezone::class)->toLocal(now())->toDateString();
        }
    }

    public function updatedWeek(): void
    {
        $this->resetPage();
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    protected function window(): array
    {
        $this->validate(['week' => ['required', 'date_format:Y-m-d']]);
        $start = CarbonImmutable::parse($this->week, app(Timezone::class)->current())->startOfDay()->utc();

        return [$start, $start->addWeek()];
    }

    /** @return LengthAwarePaginator<int, CrewShift> */
    #[Computed]
    public function shifts(): LengthAwarePaginator
    {
        Gate::authorize('viewAny', CrewShift::class);
        [$from,$to] = $this->window();

        return CrewShift::with(['crewMember', 'project'])->where('starts_at', '<', $to)->where('ends_at', '>', $from)->where('status', '!=', 'cancelled')->orderBy('starts_at')->paginate(30);
    }

    /** @return Collection<int, CrewAvailability> */
    #[Computed]
    public function availability(): Collection
    {
        Gate::authorize('viewAny', CrewAvailability::class);
        [$from,$to] = $this->window();

        return CrewAvailability::with('crewMember')->where('starts_at', '<', $to)->where('ends_at', '>', $from)->whereIn('status', ['requested', 'approved'])->orderBy('starts_at')->limit(100)->get();
    }

    public function render(): View
    {
        return view('crew::calendar');
    }
}
