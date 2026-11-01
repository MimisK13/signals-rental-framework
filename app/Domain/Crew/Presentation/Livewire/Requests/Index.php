<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Livewire\Requests;

use App\Domain\Crew\Actions\Requests\CreateCrewRequest;
use App\Domain\Crew\Data\Requests\CreateCrewRequestData;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Presentation\Livewire\CrewComponent;
use App\Models\Project;
use App\Models\Store;
use App\Support\Timezone;
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
 * @property-read LengthAwarePaginator<int, CrewRequest> $requests
 * @property-read Collection<int, Project> $projects
 * @property-read Collection<int, Store> $stores
 */
#[Layout('components.layouts.app')]
#[Title('Crew requests')]
final class Index extends CrewComponent
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    public bool $creating = false;

    public string $title = '';

    public ?int $project_id = null;

    public ?int $store_id = null;

    public string $starts_at = '';

    public string $ends_at = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', CrewRequest::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<int, CrewRequest> */
    #[Computed]
    public function requests(): LengthAwarePaginator
    {
        Gate::authorize('viewAny', CrewRequest::class);
        $query = CrewRequest::with(['project', 'lines'])->orderByDesc('starts_at');
        if ($this->search !== '') {
            $query->whereLike('title', '%'.$this->search.'%');
        }
        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        return $query->paginate(25);
    }

    /** @return Collection<int, Project> */
    #[Computed]
    public function projects(): Collection
    {
        Gate::authorize('create', CrewRequest::class);

        return Project::orderBy('name')->get(['id', 'name']);
    }

    /** @return Collection<int, Store> */
    #[Computed]
    public function stores(): Collection
    {
        Gate::authorize('create', CrewRequest::class);
        $query = Store::orderBy('name');
        $ids = auth()->user()->accessibleStoreIds();
        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->get(['id', 'name']);
    }

    public function save(): void
    {
        Gate::authorize('create', CrewRequest::class);
        $data = $this->validate(['title' => ['required', 'string', 'max:255'], 'project_id' => ['required', 'integer', 'exists:projects,id'], 'store_id' => ['required', 'integer', 'exists:stores,id'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at']]);
        [$data['starts_at'], $data['ends_at']] = $this->crewRangeToUtc($data['starts_at'], $data['ends_at']);
        $data['timezone'] = app(Timezone::class)->current();
        $this->performCrewAction(function () use ($data): void {
            $request = app(CreateCrewRequest::class)(CreateCrewRequestData::from($data));
            $this->redirectRoute('crew.requests.show', ['crewRequest' => $request->id], navigate: true);
        });
    }

    public function render(): View
    {
        return view('crew::requests.index');
    }
}
