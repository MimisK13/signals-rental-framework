<?php

declare(strict_types=1);

namespace App\Domain\Lists\Presentation\Livewire;

use App\Domain\Lists\Actions\CreateListValue;
use App\Domain\Lists\Actions\UpdateListValue;
use App\Domain\Lists\Data\CreateListValueData;
use App\Domain\Lists\Data\UpdateListValueData;
use App\Domain\Lists\Models\ListName;
use App\Domain\Lists\Models\ListValue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('List Value')]
final class ListValueForm extends Component
{
    public ListName $listName;

    public ?int $listValueId = null;

    public string $name = '';

    public int $sortOrder = 0;

    public bool $isActive = true;

    public ?int $parentId = null;

    public function mount(ListName $listName, ?ListValue $listValue = null): void
    {
        $this->listName = $listName;

        if ($listValue?->exists) {
            $listValue = $listName->values()->findOrFail($listValue->getKey());
            $this->listValueId = $listValue->id;
            $this->name = $listValue->name;
            $this->sortOrder = $listValue->sort_order ?? 0;
            $this->isActive = $listValue->is_active;
            $this->parentId = $listValue->parent_id;
        }
    }

    public function save(): void
    {
        $data = [
            'name' => $this->name,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
        ];

        if ($this->listName->is_hierarchical) {
            $data['parent_id'] = $this->parentId;
        }

        if ($this->listValueId !== null) {
            $value = $this->listName->values()->findOrFail($this->listValueId);
            (new UpdateListValue)($value, UpdateListValueData::validateAndCreate($data));
        } else {
            $data['list_name_id'] = $this->listName->id;
            (new CreateListValue)(CreateListValueData::validateAndCreate($data));
        }

        $this->redirect(route('admin.settings.lists', $this->listName), navigate: true);
    }

    /** @return Collection<int, ListValue> */
    private function parentOptions(): Collection
    {
        if (! $this->listName->is_hierarchical) {
            return new Collection;
        }

        return $this->listName->values()
            ->when($this->listValueId !== null, fn ($query) => $query->where('id', '!=', $this->listValueId))
            ->orderBy('sort_order')
            ->get(['id', 'name']);
    }

    public function render(): View
    {
        return view('lists::list-value-form', [
            'isEditing' => $this->listValueId !== null,
            'parentOptions' => $this->parentOptions(),
        ]);
    }
}
