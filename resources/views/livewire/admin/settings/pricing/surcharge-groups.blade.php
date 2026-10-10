<?php

use App\Actions\Pricing\DeleteSurchargeGroup;
use App\Models\SurchargeGroup;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Surcharge Groups')] class extends Component {
    /** @var \Illuminate\Database\Eloquent\Collection<int, SurchargeGroup> */
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
}; ?>

<section class="w-full">
    <x-admin.layout group="pricing" title="Surcharge Groups" description="Organise automatic line surcharges into toggleable groups.">
        <x-slot:actions>
            @can('pricing.manage')
                <flux:button variant="primary" href="{{ route('admin.settings.pricing.surcharge-groups.create') }}" wire:navigate.hover>
                    Add Surcharge Group
                </flux:button>
            @endcan
        </x-slot:actions>

        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Default</th>
                        <th>Status</th>
                        <th class="w-[120px]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($groups as $group)
                        <tr wire:key="surcharge-group-{{ $group->id }}">
                            <td class="font-medium">{{ $group->name }}</td>
                            <td>
                                @if($group->is_default)
                                    <span class="s-badge s-badge-blue">Default</span>
                                @else
                                    <span class="s-badge">Optional</span>
                                @endif
                            </td>
                            <td>
                                @if($group->is_active)
                                    <span class="s-badge s-badge-green">Active</span>
                                @else
                                    <span class="s-badge">Inactive</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @can('pricing.manage')
                                    <a href="{{ route('admin.settings.pricing.surcharge-groups.edit', $group) }}" wire:navigate.hover class="s-btn s-btn-ghost s-btn-sm" title="Edit">
                                        <flux:icon.pencil-square class="w-4 h-4" />
                                    </a>
                                    <button wire:click="deleteGroup({{ $group->id }})"
                                            wire:confirm="Delete this surcharge group?"
                                            class="s-btn-ghost s-btn-xs text-[var(--red)]">
                                        Delete
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-[var(--text-muted)]">No surcharge groups configured.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.layout>
</section>
