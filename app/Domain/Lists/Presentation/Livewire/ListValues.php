<?php

declare(strict_types=1);

namespace App\Domain\Lists\Presentation\Livewire;

use App\Domain\Lists\Actions\DeleteListValue;
use App\Domain\Lists\Actions\UpdateListValue;
use App\Domain\Lists\Data\UpdateListValueData;
use App\Domain\Lists\Models\ListName;
use App\Domain\Lists\Models\ListValue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Lists')]
final class ListValues extends Component
{
    public ListName $listName;

    public function mount(ListName $listName): void
    {
        $this->listName = $listName;
    }

    public function toggleActive(int $valueId): void
    {
        $value = $this->listName->values()->findOrFail($valueId);

        (new UpdateListValue)($value, UpdateListValueData::validateAndCreate([
            'is_active' => ! $value->is_active,
        ]));
    }

    public function deleteValue(int $valueId): void
    {
        $value = $this->listName->values()->findOrFail($valueId);

        if ($value->is_system) {
            $this->addError('delete', 'System values cannot be deleted.');

            return;
        }

        (new DeleteListValue)($value);
        $this->dispatch('value-deleted');
    }

    /** @return Collection<int, ListValue> */
    private function values(): Collection
    {
        return $this->listName->values()
            ->with('parent')
            ->orderBy('sort_order')
            ->get();
    }

    public function render(): View
    {
        return view('lists::list-values', [
            'values' => $this->values(),
        ]);
    }
}
