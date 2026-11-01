<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Livewire\Members;

use App\Domain\Crew\Actions\Registry\ArchiveCrewMember;
use App\Domain\Crew\Actions\Registry\RestoreCrewMember;
use App\Domain\Crew\Models\CrewMember;
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
 * @property-read LengthAwarePaginator<int, CrewMember> $members
 */
#[Layout('components.layouts.app')]
#[Title('Crew members')]
final class Index extends CrewComponent
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'active';

    public function mount(): void
    {
        Gate::authorize('viewAny', CrewMember::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<int, CrewMember> */
    #[Computed]
    public function members(): LengthAwarePaginator
    {
        Gate::authorize('viewAny', CrewMember::class);
        $query = CrewMember::with(['roles', 'skills'])->orderBy('name');
        if ($this->status === 'archived') {
            $query->onlyTrashed();
        } elseif ($this->status !== 'all') {
            $query->where('status', in_array($this->status, ['active', 'inactive'], true) ? $this->status : 'active');
        } else {
            $query->withTrashed();
        }
        if ($this->search !== '') {
            $query->whereLike('name', '%'.$this->search.'%');
        }

        return $query->paginate(25);
    }

    public function archive(int $id): void
    {
        $this->performCrewAction(function () use ($id): void {
            app(ArchiveCrewMember::class)(CrewMember::findOrFail($id));
        });
    }

    public function restore(int $id): void
    {
        $this->performCrewAction(function () use ($id): void {
            app(RestoreCrewMember::class)(CrewMember::onlyTrashed()->findOrFail($id));
        });
    }

    public function render(): View
    {
        return view('crew::members.index');
    }
}
