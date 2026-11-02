<?php

declare(strict_types=1);

namespace App\Domain\Lists\Presentation\Livewire;

use App\Domain\Lists\Actions\DeleteListName;
use App\Domain\Lists\Models\ListName;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('List Names')]
final class ListNames extends Component
{
    public function deleteList(int $listNameId): void
    {
        $listName = ListName::findOrFail($listNameId);

        if ($listName->is_system) {
            $this->addError('delete', 'System lists cannot be deleted.');

            return;
        }

        (new DeleteListName)($listName);
        $this->dispatch('list-deleted');
    }

    /** @return Collection<int, ListName> */
    private function listNames(): Collection
    {
        return ListName::query()
            ->withCount('values')
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        return view('lists::list-names', [
            'listNames' => $this->listNames(),
        ]);
    }
}
