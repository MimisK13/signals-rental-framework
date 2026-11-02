<?php

declare(strict_types=1);

namespace App\Domain\CustomFields\Presentation\Livewire;

use App\Domain\CustomFields\Actions\CreateCustomFieldGroup;
use App\Domain\CustomFields\Actions\UpdateCustomFieldGroup;
use App\Domain\CustomFields\Data\CreateCustomFieldGroupData;
use App\Domain\CustomFields\Data\UpdateCustomFieldGroupData;
use App\Domain\CustomFields\Models\CustomFieldGroup;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Custom Field Group')]
final class GroupForm extends Component
{
    public ?int $groupId = null;

    public string $name = '';

    public string $description = '';

    public int $sortOrder = 0;

    public function mount(?CustomFieldGroup $customFieldGroup = null): void
    {
        if ($customFieldGroup?->exists) {
            $this->groupId = $customFieldGroup->id;
            $this->name = $customFieldGroup->name;
            $this->description = $customFieldGroup->description ?? '';
            $this->sortOrder = $customFieldGroup->sort_order ?? 0;
        }
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'isEditing' => $this->groupId !== null,
        ];
    }

    public function save(): void
    {
        if ($this->groupId) {
            $group = CustomFieldGroup::findOrFail($this->groupId);
            (new UpdateCustomFieldGroup)($group, UpdateCustomFieldGroupData::validateAndCreate([
                'name' => $this->name,
                'description' => $this->description ?: null,
                'sort_order' => $this->sortOrder,
            ]));
        } else {
            (new CreateCustomFieldGroup)(CreateCustomFieldGroupData::validateAndCreate([
                'name' => $this->name,
                'description' => $this->description ?: null,
                'sort_order' => $this->sortOrder,
            ]));
        }

        $this->redirect(route('admin.settings.custom-field-groups'), navigate: true);
    }

    public function render(): View
    {
        return view('custom-fields::group-form', $this->with());
    }
}
