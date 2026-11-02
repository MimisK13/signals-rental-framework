<?php

declare(strict_types=1);

namespace App\Domain\CustomFields\Presentation\Livewire;

use App\Domain\CustomFields\Actions\DeleteCustomFieldGroup;
use App\Domain\CustomFields\Models\CustomFieldGroup;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Custom Field Groups')]
final class GroupsIndex extends Component
{
    public function deleteGroup(int $groupId): void
    {
        $group = CustomFieldGroup::findOrFail($groupId);

        if ($group->customFields()->count() > 0) {
            $this->addError('delete', 'Cannot delete a group that still has custom fields.');

            return;
        }

        (new DeleteCustomFieldGroup)($group);
        $this->dispatch('group-deleted');
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'groups' => CustomFieldGroup::query()
                ->withCount('customFields')
                ->orderBy('sort_order')
                ->get(),
        ];
    }

    public function render(): View
    {
        return view('custom-fields::groups-index', $this->with());
    }
}
