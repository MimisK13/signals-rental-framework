<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Presentation\Livewire;

use App\Domain\Projects\Opportunities\Actions\DeleteOpportunity;
use App\Domain\Projects\Opportunities\Actions\RestoreOpportunity;
use App\Domain\Projects\Opportunities\Enums\OpportunityState;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Presentation\Columns\OpportunityColumnRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Opportunities')] final class Index extends Component
{
    #[Url(as: 'state')]
    public string $stateFilter = '';

    #[Url(as: 'archive')]
    public string $archiveFilter = 'active';

    /** Filters the list to opportunities flagged with a shortage. Deep-linkable via ?has_shortage=1. */
    #[Url(as: 'has_shortage')]
    public bool $shortageFilter = false;

    /** @var Collection<int, int> State value => row count. */
    public Collection $stateCounts;

    public int $totalCount = 0;

    public int $shortageCount = 0;

    public function mount(): void
    {
        Gate::authorize('opportunities.access');

        $state = request()->query('state', '');
        if ($state !== '' && OpportunityState::tryFrom((int) $state)) {
            $this->stateFilter = (string) $state;
        }

        $this->refreshStateCounts();
    }

    public function setStateFilter(string $state): void
    {
        if ($state !== '' && OpportunityState::tryFrom((int) $state) === null) {
            return;
        }

        $this->stateFilter = $state;
    }

    public function archiveOpportunity(int $id): void
    {
        $opportunity = Opportunity::findOrFail($id);
        (new DeleteOpportunity)($opportunity);
        $this->refreshStateCounts();
        $this->dispatch('opportunity-archived');
    }

    public function restoreOpportunity(int $id): void
    {
        $opportunity = Opportunity::withTrashed()->findOrFail($id);
        (new RestoreOpportunity)($opportunity);
        $this->refreshStateCounts();
        $this->dispatch('opportunity-restored');
    }

    /**
     * @param  array<int, int>  $ids
     */
    public function archiveSelected(array $ids): void
    {
        $opportunities = Opportunity::whereIn('id', $ids)->get();

        DB::transaction(function () use ($opportunities): void {
            foreach ($opportunities as $opportunity) {
                (new DeleteOpportunity)($opportunity);
            }
        });

        $this->refreshStateCounts();
        $this->dispatch('opportunity-archived');
    }

    /**
     * @param  array<int, int>  $ids
     */
    public function restoreSelected(array $ids): void
    {
        $opportunities = Opportunity::withTrashed()->whereIn('id', $ids)->get();

        DB::transaction(function () use ($opportunities): void {
            foreach ($opportunities as $opportunity) {
                (new RestoreOpportunity)($opportunity);
            }
        });

        $this->refreshStateCounts();
        $this->dispatch('opportunity-restored');
    }

    #[On('opportunity-archived')]
    #[On('opportunity-restored')]
    public function refreshStateCounts(): void
    {
        $baseQuery = fn () => match ($this->archiveFilter) {
            'archived' => Opportunity::onlyTrashed(),
            'all' => Opportunity::withTrashed(),
            default => Opportunity::query(),
        };

        $query = $baseQuery();

        if ($this->shortageFilter) {
            $query->withShortage();
        }

        $this->stateCounts = $query
            ->selectRaw('state, count(*) as count')
            ->groupBy('state')
            ->pluck('count', 'state');

        $this->totalCount = $this->stateCounts->sum();

        // Count of shortage rows in the current archive scope, regardless of the
        // shortage toggle, so the chip can always show how many would match.
        $this->shortageCount = (int) $baseQuery()->withShortage()->count();
    }

    public function toggleShortageFilter(): void
    {
        $this->shortageFilter = ! $this->shortageFilter;
        $this->refreshStateCounts();
    }

    public function setArchiveFilter(string $filter): void
    {
        if (! in_array($filter, ['active', 'archived', 'all'])) {
            return;
        }

        $this->archiveFilter = $filter;
        $this->refreshStateCounts();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $stateOptions = collect(OpportunityState::cases())
            ->mapWithKeys(fn (OpportunityState $s): array => [$s->value => $s->label()])
            ->all();

        // Derive sortable/filterable flags from the canonical OpportunityColumnRegistry
        // so the table column metadata cannot drift from the registry that drives
        // custom views and the column picker. Only columns mapping 1:1 to a real
        // `opportunities` column take their flags from the registry.
        $registry = app(OpportunityColumnRegistry::class);
        $columnMeta = fn (string $key): array => [
            'sortable' => $registry->get($key)->sortable,
            'filterable' => $registry->get($key)->filterable,
        ];

        return [
            'opportunityStates' => [
                OpportunityState::Draft,
                OpportunityState::Quotation,
                OpportunityState::Order,
            ],
            'totalCount' => $this->totalCount,
            'stateCounts' => $this->stateCounts,
            'columns' => [
                ['key' => 'checkbox', 'type' => 'checkbox'],
                ['key' => 'subject', 'label' => 'Subject', ...$columnMeta('subject'), 'filter_type' => 'text', 'view' => 'project-opportunities::partials.column-subject'],
                ['key' => 'reference', 'label' => 'Reference', ...$columnMeta('reference'), 'filter_type' => 'text', 'view' => 'project-opportunities::partials.column-reference'],
                ['key' => 'state', 'label' => 'State', ...$columnMeta('state'), 'filter_type' => 'select', 'filter_options' => $stateOptions, 'view' => 'project-opportunities::partials.column-state'],
                ['key' => 'status', 'label' => 'Status', ...$columnMeta('status'), 'view' => 'project-opportunities::partials.column-status'],
                ['key' => 'starts_at', 'label' => 'Starts', ...$columnMeta('starts_at'), 'view' => 'project-opportunities::partials.column-starts-at'],
                ['key' => 'ends_at', 'label' => 'Ends', ...$columnMeta('ends_at'), 'view' => 'project-opportunities::partials.column-ends-at'],
                ['key' => 'charge_total', 'label' => 'Charge Total', ...$columnMeta('charge_total'), 'view' => 'project-opportunities::partials.column-charge-total'],
                ['key' => 'created_at', 'label' => 'Created', ...$columnMeta('created_at'), 'view' => 'project-opportunities::partials.column-created'],
                ['key' => 'actions', 'type' => 'actions'],
            ],
            'shortageCount' => $this->shortageCount,
            'scopes' => [
                ...($this->stateFilter !== '' ? ['ofState' => OpportunityState::from((int) $this->stateFilter)] : []),
                ...($this->shortageFilter ? ['withShortage' => true] : []),
                ...match ($this->archiveFilter) {
                    'archived' => ['archived' => true],
                    'all' => ['withArchived' => true],
                    default => [],
                },
            ],
        ];
    }

    public function render(): View
    {
        return view('project-opportunities::index');
    }
}
