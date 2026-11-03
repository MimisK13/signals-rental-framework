<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Presentation\Livewire;

use App\Domain\Pricing\Actions\DeleteSurchargeGroup;
use App\Domain\Pricing\Models\SurchargeGroup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Surcharge Groups')] final class SurchargeGroups extends Component
{
    /** @var Collection<int, SurchargeGroup> */
    public $groups;

    public function mount(): void
    {
        $this->loadGroups();
    }

    public function loadGroups(): void
    {
        $this->groups = SurchargeGroup::query()->orderBy('sort_order')->orderBy('name')->get();
    }

    public function deleteGroup(int $groupId): void
    {
        $group = SurchargeGroup::findOrFail($groupId);

        (new DeleteSurchargeGroup)($group);
        $this->loadGroups();
    }

    public function render(): View
    {
        return view('pricing::admin.surcharge-groups');
    }
}
