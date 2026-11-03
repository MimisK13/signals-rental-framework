<?php

declare(strict_types=1);

namespace App\Domain\Activities\Presentation\Livewire\Calendar;

use App\Domain\Activities\Data\Calendar\CalendarEventData;
use App\Domain\Activities\Models\Activity;
use App\Domain\Activities\Services\Calendar\CalendarEventService;
use App\Domain\Administration\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * @property-read Collection<int, CalendarEventData> $events
 * @property-read Collection<int, CalendarEventData> $unscheduledEvents
 * @property-read array{staff: int, activities: int, completed: int} $stats
 * @property-read Collection<int, User> $staff
 */
#[Layout('components.layouts.app')]
#[Title('Calendar')]
final class Index extends Component
{
    #[Url]
    public string $view = 'week';

    #[Url]
    public string $startDate = '';

    /** @var list<int> */
    #[Url]
    public array $ownerIds = [];

    /**
     * Whether the heavy events query has run. The first paint renders a skeleton
     * (fast), then `wire:init="load"` flips this so the grid queries and renders.
     */
    public bool $loaded = false;

    public int $staffPage = 0;

    private const DAY_COLUMNS_PER_PAGE = 10;

    public function mount(): void
    {
        abort_unless(Gate::allows('activities.access'), 403);

        if ($this->startDate === '') {
            $this->startDate = today()->toDateString();
        }

        if (! in_array($this->view, ['day', 'week', 'month'], true)) {
            $this->view = 'week';
        }
    }

    /**
     * Flip the loaded flag so the grid renders. Triggered by `wire:init` after the
     * initial skeleton paint, deferring the heavy events query off the first render.
     */
    public function load(): void
    {
        $this->loaded = true;
    }

    public function setView(string $view): void
    {
        if (in_array($view, ['day', 'week', 'month'], true)) {
            $this->view = $view;
        }
    }

    public function goToday(): void
    {
        $this->startDate = today()->toDateString();
    }

    public function prev(): void
    {
        $this->step(-1);
    }

    public function next(): void
    {
        $this->step(1);
    }

    public function openDayView(string $date): void
    {
        $this->startDate = $date;
        $this->view = 'day';
    }

    public function toggleOwner(int $userId): void
    {
        $allIds = $this->staff()->pluck('id')->map(fn ($id): int => (int) $id)->all();

        // An empty filter means "all users selected" — start from that set.
        $current = $this->ownerIds === [] ? $allIds : $this->ownerIds;

        if (in_array($userId, $current, true)) {
            $current = array_values(array_filter($current, fn (int $id): bool => $id !== $userId));
        } else {
            $current = [...$current, $userId];
        }

        sort($current);
        sort($allIds);

        // Collapse back to "all" (empty) when every user is selected again.
        $this->ownerIds = ($current === $allIds) ? [] : $current;
        $this->staffPage = 0;
    }

    public function clearOwners(): void
    {
        $this->ownerIds = [];
        $this->staffPage = 0;
    }

    public function prevStaffPage(): void
    {
        $this->staffPage = max(0, $this->staffPage - 1);
    }

    public function nextStaffPage(): void
    {
        $this->staffPage = min($this->dayStaffPageCount() - 1, $this->staffPage + 1);
    }

    #[On('calendar-refresh')]
    public function refresh(): void
    {
        // Re-render only; computed properties recompute and re-query events.
        unset($this->events, $this->unscheduledEvents, $this->stats);
    }

    /**
     * Step the visible window by one unit of the current view.
     */
    private function step(int $direction): void
    {
        $date = Carbon::parse($this->startDate);

        $this->startDate = match ($this->view) {
            'day' => $date->addDays($direction)->toDateString(),
            'month' => $date->addMonthsNoOverflow($direction)->toDateString(),
            default => $date->addWeeks($direction)->toDateString(),
        };
    }

    /**
     * First day of the configured week (0=Sun..6=Sat, default 1=Mon).
     */
    private function weekStart(): int
    {
        return (int) settings('preferences.first_day_of_week');
    }

    /**
     * Inclusive [from, to] window that the current view renders.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function range(): array
    {
        $date = Carbon::parse($this->startDate);

        return match ($this->view) {
            'day' => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
            'month' => [
                $date->copy()->startOfMonth()->startOfWeek($this->weekStart())->startOfDay(),
                $date->copy()->endOfMonth()->endOfWeek($this->weekStartEnd())->endOfDay(),
            ],
            default => [
                $date->copy()->startOfWeek($this->weekStart())->startOfDay(),
                $date->copy()->startOfWeek($this->weekStart())->addDays(6)->endOfDay(),
            ],
        };
    }

    /**
     * The end-of-week day index paired with the configured week start.
     */
    private function weekStartEnd(): int
    {
        return ($this->weekStart() + 6) % 7;
    }

    /**
     * Visible day cursors for the week/month grids.
     *
     * @return list<CarbonInterface>
     */
    public function visibleDays(): array
    {
        [$from, $to] = $this->range();

        $days = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $days[] = $cursor->copy();
            $cursor->addDay();
        }

        return $days;
    }

    /**
     * @return Collection<int, CalendarEventData>
     */
    #[Computed]
    public function events(): Collection
    {
        [$from, $to] = $this->range();

        return app(CalendarEventService::class)
            ->scheduled($from, $to, $this->ownerIds)
            ->map(fn (Activity $activity): CalendarEventData => CalendarEventData::fromModel($activity));
    }

    /**
     * @return Collection<int, CalendarEventData>
     */
    #[Computed]
    public function unscheduledEvents(): Collection
    {
        return app(CalendarEventService::class)
            ->unscheduled($this->ownerIds)
            ->map(fn (Activity $activity): CalendarEventData => CalendarEventData::fromModel($activity));
    }

    /**
     * @return array{staff: int, activities: int, completed: int}
     */
    #[Computed]
    public function stats(): array
    {
        [$from, $to] = $this->range();
        $counts = app(CalendarEventService::class)->rangeStats($from, $to, $this->ownerIds);

        return [
            'staff' => $this->visibleStaff()->count(),
            'activities' => $counts['activities'],
            'completed' => $counts['completed'],
        ];
    }

    /**
     * Active staff used for the owner filter and day-view columns.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function staff(): Collection
    {
        return User::query()
            ->with('member')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * All active staff for the owner filter, logged-in user first.
     *
     * @return Collection<int, User>
     */
    public function orderedStaff(): Collection
    {
        $authId = auth()->id();
        $staff = $this->staff();

        return $staff->filter(fn (User $u): bool => $u->id === $authId)
            ->merge($staff->filter(fn (User $u): bool => $u->id !== $authId))
            ->values();
    }

    /**
     * Staff actually rendered as day-view columns (filtered subset, or all).
     *
     * @return Collection<int, User>
     */
    public function visibleStaff(): Collection
    {
        if ($this->ownerIds === []) {
            return $this->staff();
        }

        return $this->staff()->whereIn('id', $this->ownerIds)->values();
    }

    /**
     * Day-view columns ordered with the logged-in user first, then by name.
     *
     * @return Collection<int, User>
     */
    public function orderedDayStaff(): Collection
    {
        $authId = auth()->id();
        $staff = $this->visibleStaff();

        return $staff->filter(fn (User $u): bool => $u->id === $authId)
            ->merge($staff->filter(fn (User $u): bool => $u->id !== $authId))
            ->values();
    }

    /**
     * Number of day-view staff pages (max DAY_COLUMNS_PER_PAGE columns per page).
     */
    public function dayStaffPageCount(): int
    {
        return (int) max(1, (int) ceil($this->orderedDayStaff()->count() / self::DAY_COLUMNS_PER_PAGE));
    }

    /**
     * Staff columns shown on the current day-view page.
     *
     * @return Collection<int, User>
     */
    public function dayColumns(): Collection
    {
        $page = min($this->staffPage, $this->dayStaffPageCount() - 1);

        return $this->orderedDayStaff()
            ->slice($page * self::DAY_COLUMNS_PER_PAGE, self::DAY_COLUMNS_PER_PAGE)
            ->values();
    }

    /**
     * Working-hours window [startHour, endHour] (24h ints), auto-expanded to
     * include any visible event so nothing is clipped.
     *
     * @return array{0: int, 1: int}
     */
    public function hourWindow(): array
    {
        $start = (int) Carbon::parse((string) settings('scheduling.default_start_time'))->format('H');
        $end = (int) Carbon::parse((string) settings('scheduling.default_end_time'))->format('H');

        if ($end <= $start) {
            $end = $start + 1;
        }

        foreach ($this->events() as $event) {
            if ($event->all_day || $event->starts_at === null) {
                continue;
            }

            $eventStart = (int) Carbon::parse($event->starts_at)->format('H');
            $eventEndCarbon = $event->ends_at !== null
                ? Carbon::parse($event->ends_at)
                : Carbon::parse($event->starts_at)->addMinutes(30);
            // Round the end hour up so the trailing minutes are not clipped.
            $eventEnd = (int) $eventEndCarbon->format('H') + ($eventEndCarbon->format('i') !== '00' ? 1 : 0);

            $start = min($start, $eventStart);
            $end = max($end, $eventEnd);
        }

        return [max(0, $start), min(24, max($end, $start + 1))];
    }

    /**
     * Whether weekends should be shaded as non-working.
     */
    public function weekendShaded(): bool
    {
        return ! (bool) settings('scheduling.weekend_availability');
    }

    public function render(): View
    {
        return view('activities::calendar.index');
    }
}
