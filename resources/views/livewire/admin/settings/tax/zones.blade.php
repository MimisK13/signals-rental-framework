<?php

use App\Actions\Tax\DeleteTaxZone;
use App\Models\TaxZone;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Tax Zones')] class extends Component {
    /** @var \Illuminate\Database\Eloquent\Collection<int, TaxZone> */
    public $taxZones;

    public function mount(): void
    {
        $this->loadTaxZones();
    }

    public function loadTaxZones(): void
    {
        $this->taxZones = TaxZone::query()->withCount('members')->orderByDesc('priority')->orderBy('name')->get();
    }

    public function deleteTaxZone(int $taxZoneId): void
    {
        $taxZone = TaxZone::findOrFail($taxZoneId);
        (new DeleteTaxZone)($taxZone);
        $this->loadTaxZones();
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="tax" title="Tax Zones" description="Geographic zones used for cross-border tax rule matching.">
        <x-slot:actions>
            <flux:button variant="primary" href="{{ route('admin.settings.tax.zones.create') }}" wire:navigate>
                Add Tax Zone
            </flux:button>
        </x-slot:actions>

        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Countries</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th class="w-[120px]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($taxZones as $taxZone)
                        <tr wire:key="tax-zone-{{ $taxZone->id }}">
                            <td class="font-medium">{{ $taxZone->name }}</td>
                            <td><code>{{ $taxZone->slug }}</code></td>
                            <td>{{ $taxZone->members_count }}</td>
                            <td>{{ $taxZone->priority }}</td>
                            <td>
                                @if($taxZone->is_active)
                                    <span class="s-badge s-badge-green">Active</span>
                                @else
                                    <span class="s-badge s-badge-zinc">Inactive</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('admin.settings.tax.zones.edit', $taxZone) }}" wire:navigate class="s-btn s-btn-ghost s-btn-sm" title="Edit">
                                    <flux:icon.pencil-square class="w-4 h-4" />
                                </a>
                                <button wire:click="deleteTaxZone({{ $taxZone->id }})"
                                        wire:confirm="Delete this tax zone?"
                                        class="s-btn-ghost s-btn-xs text-[var(--red)]">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-[var(--text-muted)]">No tax zones configured.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.layout>
</section>
