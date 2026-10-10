<?php

use App\Actions\Pricing\DeleteBundleRule;
use App\Models\BundleRule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Bundle Rules')] class extends Component {
    /** @var \Illuminate\Database\Eloquent\Collection<int, BundleRule> */
    public $rules;

    public function mount(): void
    {
        $this->loadRules();
    }

    public function loadRules(): void
    {
        $this->rules = BundleRule::query()->orderBy('sort_order')->orderBy('name')->get();
    }

    public function deleteRule(int $ruleId): void
    {
        $rule = BundleRule::findOrFail($ruleId);

        (new DeleteBundleRule)($rule);
        $this->loadRules();
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="pricing" title="Bundle Rules" description="Stage 1.5 bundle pricing — fixed or percentage bundles applied at the revenue-group level before discounts.">
        <x-slot:actions>
            @can('pricing.manage')
                <flux:button variant="primary" href="{{ route('admin.settings.pricing.bundle-rules.create') }}" wire:navigate.hover>
                    Add Bundle Rule
                </flux:button>
            @endcan
        </x-slot:actions>

        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Pricing</th>
                        <th>Status</th>
                        <th class="w-[120px]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rules as $rule)
                        <tr wire:key="bundle-rule-{{ $rule->id }}">
                            <td class="font-medium">{{ $rule->name }}</td>
                            <td>
                                @if($rule->isFixed())
                                    Fixed {{ number_format($rule->bundle_fixed_price_minor / 100, 2) }}
                                @else
                                    {{ $rule->bundle_percent }}%
                                @endif
                            </td>
                            <td>
                                @if($rule->is_active)
                                    <span class="s-badge s-badge-green">Active</span>
                                @else
                                    <span class="s-badge">Inactive</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @can('pricing.manage')
                                    <a href="{{ route('admin.settings.pricing.bundle-rules.edit', $rule) }}" wire:navigate.hover class="s-btn s-btn-ghost s-btn-sm" title="Edit">
                                        <flux:icon.pencil-square class="w-4 h-4" />
                                    </a>
                                    <button wire:click="deleteRule({{ $rule->id }})"
                                            wire:confirm="Delete this bundle rule?"
                                            class="s-btn-ghost s-btn-xs text-[var(--red)]">
                                        Delete
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-[var(--text-muted)] py-8">No bundle rules yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.layout>
</section>
