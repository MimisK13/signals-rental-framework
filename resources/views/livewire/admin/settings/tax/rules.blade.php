<?php

use App\Actions\Tax\DeleteTaxRule;
use App\Models\TaxRule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Tax Rules')] class extends Component {
    /** @var \Illuminate\Database\Eloquent\Collection<int, TaxRule> */
    public $taxRules;

    public function mount(): void
    {
        $this->loadTaxRules();
    }

    public function loadTaxRules(): void
    {
        $this->taxRules = TaxRule::query()
            ->with(['organisationTaxClass', 'productTaxClass', 'taxRate', 'sellerZone', 'buyerZone', 'taxCategory'])
            ->orderByDesc('priority')
            ->get();
    }

    public function deleteTaxRule(int $taxRuleId): void
    {
        $taxRule = TaxRule::findOrFail($taxRuleId);
        (new DeleteTaxRule)($taxRule);
        $this->loadTaxRules();
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="tax" title="Tax Rules" description="Class-mapping rules and zone-matching rules for cross-border tax treatment.">
        <x-slot:actions>
            <flux:button variant="ghost" href="{{ route('admin.settings.tax.rules.create') }}" wire:navigate>
                Add Class Rule
            </flux:button>
            <flux:button variant="primary" href="{{ route('admin.settings.tax.rules.create-zone') }}" wire:navigate>
                Add Zone Rule
            </flux:button>
        </x-slot:actions>

        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Name / Mapping</th>
                        <th>Zones</th>
                        <th>Buyer</th>
                        <th>Result</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th class="w-[120px]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($taxRules as $taxRule)
                        <tr wire:key="tax-rule-{{ $taxRule->id }}">
                            <td>{{ $taxRule->isZoneRule() ? 'Zone' : 'Class' }}</td>
                            <td>
                                @if($taxRule->isZoneRule())
                                    {{ $taxRule->name }}
                                @else
                                    {{ $taxRule->organisationTaxClass?->name }} / {{ $taxRule->productTaxClass?->name }} → {{ $taxRule->taxRate?->name }}
                                @endif
                            </td>
                            <td>
                                @if($taxRule->isZoneRule())
                                    {{ $taxRule->sellerZone?->name }} → {{ $taxRule->buyerZone?->name }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if($taxRule->isZoneRule())
                                    {{ $taxRule->buyer_type?->label() ?? 'Any' }}
                                    @if($taxRule->has_tax_id !== null)
                                        / Tax ID: {{ $taxRule->has_tax_id ? 'Yes' : 'No' }}
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $taxRule->result_type?->label() ?? ($taxRule->taxRate?->name . ' (' . number_format((float) $taxRule->taxRate?->rate, 2) . '%)') }}</td>
                            <td>{{ $taxRule->priority }}</td>
                            <td>
                                @if($taxRule->is_active)
                                    <span class="s-badge s-badge-green">Active</span>
                                @else
                                    <span class="s-badge s-badge-zinc">Inactive</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @if($taxRule->isZoneRule())
                                    <a href="{{ route('admin.settings.tax.rules.edit-zone', $taxRule) }}" wire:navigate class="s-btn s-btn-ghost s-btn-sm" title="Edit">
                                        <flux:icon.pencil-square class="w-4 h-4" />
                                    </a>
                                @else
                                    <a href="{{ route('admin.settings.tax.rules.edit', $taxRule) }}" wire:navigate class="s-btn s-btn-ghost s-btn-sm" title="Edit">
                                        <flux:icon.pencil-square class="w-4 h-4" />
                                    </a>
                                @endif
                                <button wire:click="deleteTaxRule({{ $taxRule->id }})"
                                        wire:confirm="Are you sure you want to delete this tax rule?"
                                        class="s-btn-ghost s-btn-xs text-[var(--red)]">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-[var(--text-muted)]">No tax rules configured.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.layout>
</section>
