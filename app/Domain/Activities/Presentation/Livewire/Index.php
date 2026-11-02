<?php

declare(strict_types=1);

namespace App\Domain\Activities\Presentation\Livewire;

use App\Domain\Activities\Actions\CompleteActivity;
use App\Domain\Activities\Actions\DeleteActivity;
use App\Domain\Activities\Enums\ActivityStatus;
use App\Domain\Activities\Models\Activity;
use App\Domain\Lists\Models\ListName;
use App\Domain\Lists\Models\ListValue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Activities')]
final class Index extends Component
{
    #[Url(as: 'type')]
    public string $typeFilter = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    /** @var Collection<string, int> */
    public Collection $typeCounts;

    /** @var Collection<string, int> */
    public Collection $statusCounts;

    public int $totalCount = 0;

    /** @var Collection<int, ListValue>|null */
    private ?Collection $activityTypeValuesCache = null;

    public function mount(): void
    {
        $this->refreshCounts();
    }

    public function setTypeFilter(string $type): void
    {
        if ($type !== '' && ! $this->activityTypeValues()->contains('id', (int) $type)) {
            return;
        }

        $this->typeFilter = $type;
    }

    /**
     * Active "Activity Type" list values, ordered for filter chips/options.
     *
     * @return Collection<int, ListValue>
     */
    private function activityTypeValues(): Collection
    {
        if ($this->activityTypeValuesCache !== null) {
            return $this->activityTypeValuesCache;
        }

        return $this->activityTypeValuesCache = ListName::query()->where('name', 'Activity Type')->first()
            ?->values()->where('is_active', true)->orderBy('sort_order')->get()
            ?? collect();
    }

    public function setStatusFilter(string $status): void
    {
        if ($status !== '' && ActivityStatus::tryFrom((int) $status) === null) {
            return;
        }

        $this->statusFilter = $status;
    }

    public function deleteActivity(int $activityId): void
    {
        try {
            $activity = Activity::findOrFail($activityId);
            (new DeleteActivity)($activity);
            $this->refreshCounts();
            $this->dispatch('activity-deleted');
        } catch (ModelNotFoundException) {
            $this->refreshCounts();
            $this->dispatch('activity-deleted');
        }
    }

    public function completeActivity(int $activityId): void
    {
        try {
            $activity = Activity::findOrFail($activityId);
            (new CompleteActivity)($activity);
            $this->refreshCounts();
            $this->dispatch('activity-completed');
        } catch (ModelNotFoundException) {
            $this->refreshCounts();
            $this->dispatch('activity-completed');
        }
    }

    /**
     * Mark each selected activity complete via the CompleteActivity action.
     *
     * @param  array<int, int>  $ids
     */
    public function bulkComplete(array $ids): void
    {
        $action = new CompleteActivity;
        foreach ($ids as $id) {
            $activity = Activity::find($id);
            if ($activity && ! $activity->completed) {
                $action($activity);
            }
        }
        $this->refreshCounts();
        $this->dispatch('activity-completed');
    }

    /**
     * Delete each selected activity via the DeleteActivity action.
     *
     * @param  array<int, int>  $ids
     */
    public function bulkDelete(array $ids): void
    {
        $action = new DeleteActivity;
        foreach ($ids as $id) {
            $activity = Activity::find($id);
            if ($activity) {
                $action($activity);
            }
        }
        $this->refreshCounts();
        $this->dispatch('activity-deleted');
    }

    public function refreshCounts(): void
    {
        $this->typeCounts = Activity::query()
            ->selectRaw('type_id, count(*) as count')
            ->groupBy('type_id')
            ->pluck('count', 'type_id');

        $this->statusCounts = Activity::query()
            ->selectRaw('status_id, count(*) as count')
            ->groupBy('status_id')
            ->pluck('count', 'status_id');

        $this->totalCount = $this->typeCounts->sum();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $typeValues = $this->activityTypeValues();

        $typeOptions = $typeValues
            ->mapWithKeys(fn (ListValue $v): array => [(string) $v->id => $v->name])
            ->all();

        $statusOptions = collect(ActivityStatus::cases())
            ->mapWithKeys(fn (ActivityStatus $s): array => [(string) $s->value => $s->label()])
            ->all();

        return [
            'activityTypes' => $typeValues,
            'activityStatuses' => ActivityStatus::cases(),
            'totalCount' => $this->totalCount,
            'typeCounts' => $this->typeCounts,
            'statusCounts' => $this->statusCounts,
            'columns' => [
                ['key' => 'checkbox', 'type' => 'checkbox'],
                ['key' => 'type_id', 'label' => 'Type', 'sortable' => true, 'filterable' => true, 'filter_type' => 'select', 'filter_options' => $typeOptions, 'view' => 'activities::partials.column-type'],
                ['key' => 'subject', 'label' => 'Subject', 'sortable' => true, 'filterable' => true, 'filter_type' => 'text'],
                ['key' => 'regarding', 'label' => 'Regarding', 'view' => 'activities::partials.column-regarding'],
                ['key' => 'owner', 'label' => 'Owner', 'view' => 'activities::partials.column-owner'],
                ['key' => 'starts_at', 'label' => 'Starts', 'sortable' => true],
                ['key' => 'priority', 'label' => 'Priority', 'sortable' => true, 'view' => 'activities::partials.column-priority'],
                ['key' => 'status_id', 'label' => 'Status', 'sortable' => true, 'filterable' => true, 'filter_type' => 'select', 'filter_options' => $statusOptions, 'view' => 'activities::partials.column-status'],
                ['key' => 'created_at', 'label' => 'Created', 'sortable' => true],
                ['key' => 'actions', 'type' => 'actions'],
            ],
            'scopes' => [
                ...($this->typeFilter !== '' ? ['ofType' => (int) $this->typeFilter] : []),
                ...($this->statusFilter !== '' ? ['ofStatus' => ActivityStatus::from((int) $this->statusFilter)] : []),
            ],
        ];
    }

    public function render(): View
    {
        return view('activities::index', $this->with());
    }
}
