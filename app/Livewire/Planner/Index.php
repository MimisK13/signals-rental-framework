<?php

namespace App\Livewire\Planner;

use App\Enums\OpportunityState;
use App\Models\Opportunity;
use App\Services\Opportunities\OpportunityPresentation;
use App\Services\Planning\JobPlannerRowBuilder;
use App\Services\Planning\PlanningStoreFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Job Planner (C1) — a calendar of opportunities across dates.
 *
 * @property-read Collection<int, Opportunity> $jobs
 * @property-read array<string, int> $jobCountsPerDay
 */
#[Layout('components.layouts.app')]
#[Title('Job Planner')]
class Index extends Component
{
    private const int JOBS_LIMIT = 200;

    private const array VIEW_PERIODS = [
        '1w' => 7,
        '2w' => 14,
        '1m' => 30,
    ];

    #[Url(as: 'date')]
    public string $date = '';

    #[Url(as: 'view')]
    public string $viewPeriod = '1m';

    #[Url(as: 'preset')]
    public string $preset = '';

    #[Url(as: 'quotations')]
    public bool $includeQuotations = true;

    #[Url(as: 'orders')]
    public bool $includeOrders = true;

    /** @var list<int> */
    #[Url(as: 'stores')]
    public array $storeIds = [];

    #[Url(as: 'q')]
    public string $search = '';

    public bool $gridReady = false;

    public function mount(): void
    {
        Gate::authorize('opportunities.access');

        if ($this->date === '') {
            $this->date = Carbon::today('UTC')->toDateString();
        }

        if (! array_key_exists($this->viewPeriod, self::VIEW_PERIODS)) {
            $this->viewPeriod = '1m';
        }
    }

    public function initializeGrid(): void
    {
        $this->gridReady = true;
    }

    public function rendering(View $view): void
    {
        $view->title(__('Job Planner'));
    }

    /** @param list<string> $tagNames */
    public function syncStoreIdsFromTags(array $tagNames): void
    {
        $this->storeIds = app(PlanningStoreFilter::class)->storeIdsFromTags($tagNames);
    }

    /** @return list<string> */
    public function selectedStoreTags(): array
    {
        return app(PlanningStoreFilter::class)->selectedStoreTags($this->storeIds);
    }

    /** @return list<string> */
    public function storeNameSuggestions(): array
    {
        return app(PlanningStoreFilter::class)->storeNameSuggestions();
    }

    public function windowDays(): int
    {
        if ($this->preset === 'next_30_days') {
            return 30;
        }

        if ($this->preset === 'this_month' || $this->preset === 'next_month') {
            return $this->windowStart()->daysInMonth;
        }

        return self::VIEW_PERIODS[$this->viewPeriod] ?? 14;
    }

    public function windowStart(): Carbon
    {
        return Carbon::parse($this->date, 'UTC')->startOfDay();
    }

    public function windowEnd(): Carbon
    {
        return $this->windowStart()->copy()->addDays($this->windowDays() - 1)->endOfDay();
    }

    public function previousPeriod(): void
    {
        $this->preset = '';
        $this->date = $this->windowStart()->copy()->subDays($this->windowDays())->toDateString();
    }

    public function nextPeriod(): void
    {
        $this->preset = '';
        $this->date = $this->windowStart()->copy()->addDays($this->windowDays())->toDateString();
    }

    public function today(): void
    {
        $this->preset = '';
        $this->date = Carbon::today('UTC')->toDateString();
    }

    public function setZoom(string $period): void
    {
        if (! array_key_exists($period, self::VIEW_PERIODS)) {
            return;
        }

        $this->viewPeriod = $period;
        $this->preset = '';
    }

    public function applyPreset(string $type): void
    {
        match ($type) {
            'next_30_days' => $this->applyNext30Days(),
            'this_month' => $this->applyThisMonth(),
            'next_month' => $this->applyNextMonth(),
            default => null,
        };
    }

    public function updatedDate(): void
    {
        $this->preset = '';
    }

    private function applyNext30Days(): void
    {
        $this->date = Carbon::today('UTC')->toDateString();
        $this->viewPeriod = '1m';
        $this->preset = 'next_30_days';
    }

    private function applyThisMonth(): void
    {
        $this->date = Carbon::today('UTC')->startOfMonth()->toDateString();
        $this->preset = 'this_month';
    }

    private function applyNextMonth(): void
    {
        $this->date = Carbon::today('UTC')->addMonthNoOverflow()->startOfMonth()->toDateString();
        $this->preset = 'next_month';
    }

    /** @return list<Carbon> */
    public function dayColumns(): array
    {
        $days = [];
        $cursor = $this->windowStart();
        $end = $this->windowEnd();

        while ($cursor->lessThanOrEqualTo($end)) {
            $days[] = $cursor->copy();
            $cursor->addDay();
        }

        return $days;
    }

    /** @return Builder<Opportunity> */
    private function jobsQuery(): Builder
    {
        $states = [];
        if ($this->includeQuotations) {
            $states[] = OpportunityState::Quotation->value;
        }
        if ($this->includeOrders) {
            $states[] = OpportunityState::Order->value;
        }

        $from = $this->windowStart();
        $to = $this->windowEnd();

        return Opportunity::query()
            ->with([
                'member:id,name',
                'member.addresses' => fn ($q) => $q->orderByDesc('is_primary'),
                'venue:id,name',
                'venue.addresses' => fn ($q) => $q->orderByDesc('is_primary'),
                'store:id,name',
            ])
            ->whereIn('state', $states)
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->where('starts_at', '<=', $to)
            ->where('ends_at', '>=', $from)
            ->when($this->storeIds !== [], fn (Builder $q) => $q->whereIn('store_id', $this->storeIds))
            ->when($this->search !== '', function (Builder $q): void {
                $term = '%'.mb_strtolower($this->search).'%';
                $q->where(function (Builder $inner) use ($term): void {
                    $inner->whereRaw('lower(subject) like ?', [$term])
                        ->orWhereRaw('lower(number) like ?', [$term])
                        ->orWhereHas('member', fn (Builder $m) => $m->whereRaw('lower(name) like ?', [$term]));
                });
            })
            ->orderBy('starts_at')
            ->orderBy('id');
    }

    #[Computed]
    public function jobsTruncated(): bool
    {
        if (! $this->gridReady) {
            return false;
        }

        if (! $this->includeQuotations && ! $this->includeOrders) {
            return false;
        }

        return $this->jobsQuery()->count() > self::JOBS_LIMIT;
    }

    /** @return Collection<int, Opportunity> */
    #[Computed]
    public function jobs(): Collection
    {
        if (! $this->gridReady || (! $this->includeQuotations && ! $this->includeOrders)) {
            return collect();
        }

        return $this->jobsQuery()->limit(self::JOBS_LIMIT)->get();
    }

    /** @return array<string, int> */
    #[Computed]
    public function jobCountsPerDay(): array
    {
        if (! $this->gridReady) {
            return [];
        }

        $counts = [];
        foreach ($this->dayColumns() as $day) {
            $dayStart = $day->copy()->startOfDay();
            $dayEnd = $day->copy()->endOfDay();
            $counts[$day->toDateString()] = $this->jobs->filter(function (Opportunity $job) use ($dayStart, $dayEnd): bool {
                return $job->starts_at <= $dayEnd && $job->ends_at >= $dayStart;
            })->count();
        }

        return $counts;
    }

    #[Computed]
    public function jobCountHeatmapMax(): int
    {
        if ($this->jobCountsPerDay === []) {
            return 1;
        }

        return max(1, max($this->jobCountsPerDay));
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function rows(): array
    {
        if (! $this->gridReady) {
            return [];
        }

        return app(JobPlannerRowBuilder::class)->build(
            jobs: $this->jobs,
            windowStart: $this->windowStart(),
            windowEnd: $this->windowEnd(),
            days: $this->dayColumns(),
        );
    }

    public function cardGapPx(): int
    {
        return app(JobPlannerRowBuilder::class)->cardGapPx();
    }

    /** @return array{delivery: string, in-use: string, collection: string} */
    public function phaseColours(): array
    {
        return app(OpportunityPresentation::class)->phaseColours();
    }

    /** @return array{collecting: string, returning: string} */
    public function customerBadgeColours(): array
    {
        return app(OpportunityPresentation::class)->customerBadgeColours();
    }

    /** @return list<array{label: string, colour: string, icon: ?string, border?: bool, narrow?: bool}> */
    #[Computed]
    public function legend(): array
    {
        return app(OpportunityPresentation::class)->legend();
    }

    public function render(): View
    {
        return view('livewire.planner.index');
    }
}
