<?php

declare(strict_types=1);

namespace App\Domain\Lists\Presentation\Livewire;

use App\Domain\Lists\Actions\CreateListName;
use App\Domain\Lists\Actions\UpdateListName;
use App\Domain\Lists\Data\CreateListNameData;
use App\Domain\Lists\Data\UpdateListNameData;
use App\Domain\Lists\Models\ListName;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('List Name')]
final class ListNameForm extends Component
{
    public ?int $listNameId = null;

    public string $name = '';

    public string $description = '';

    public bool $isHierarchical = false;

    public bool $hasValues = false;

    public function mount(?ListName $listName = null): void
    {
        if ($listName?->exists) {
            $this->listNameId = $listName->id;
            $this->name = $listName->name;
            $this->description = $listName->description ?? '';
            $this->isHierarchical = $listName->is_hierarchical;
            $this->hasValues = $listName->values()->exists();
        }
    }

    public function save(): void
    {
        if ($this->listNameId !== null) {
            $listName = ListName::findOrFail($this->listNameId);

            $data = [
                'name' => $this->name,
                'description' => $this->description ?: null,
                'list_name_id' => $listName->id,
            ];

            if (! $this->hasValues) {
                $data['is_hierarchical'] = $this->isHierarchical;
            }

            (new UpdateListName)($listName, UpdateListNameData::from($data));
        } else {
            (new CreateListName)(CreateListNameData::validateAndCreate([
                'name' => $this->name,
                'description' => $this->description ?: null,
                'is_hierarchical' => $this->isHierarchical,
            ]));
        }

        $this->redirect(route('admin.settings.list-names'), navigate: true);
    }

    public function render(): View
    {
        return view('lists::list-name-form', [
            'isEditing' => $this->listNameId !== null,
        ]);
    }
}
