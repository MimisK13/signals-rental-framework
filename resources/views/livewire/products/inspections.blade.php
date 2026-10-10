<?php

use App\Models\InspectionPolicy;
use App\Actions\Inspections\CancelAssetInspection;
use App\Enums\InspectionStatus;
use App\Models\Product;
use App\Services\Assets\AssetInspectionComplianceReader;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    public Product $product;

    public ?int $editingPolicyId = null;

    public string $name = '';

    public string $description = '';

    public int $intervalMonths = 12;

    public function mount(Product $product): void
    {
        Gate::authorize('stock.view');
        abort_unless($product->stock_method === \App\Enums\StockMethod::Serialised, 404);
        $this->product = $product;
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name.' — Inspections');
    }

    public function savePolicy(): void
    {
        Gate::authorize('stock.view');
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'intervalMonths' => ['required', 'integer', 'min:1'],
        ]);

        $policy = $this->editingPolicyId
            ? InspectionPolicy::query()->where('product_id', $this->product->id)->findOrFail($this->editingPolicyId)
            : new InspectionPolicy(['product_id' => $this->product->id]);
        $policy->fill([
            'name' => $this->name,
            'description' => $this->description ?: null,
            'interval_months' => $this->intervalMonths,
            'is_active' => true,
        ])->save();

        $this->resetPolicyForm();
        $this->product->refresh();
    }

    public function editPolicy(int $policyId): void
    {
        $policy = InspectionPolicy::query()->where('product_id', $this->product->id)->findOrFail($policyId);
        $this->editingPolicyId = $policy->id;
        $this->name = $policy->name;
        $this->description = $policy->description ?? '';
        $this->intervalMonths = $policy->interval_months;
    }

    public function deactivatePolicy(int $policyId): void
    {
        Gate::authorize('stock.view');
        $policy = InspectionPolicy::query()->where('product_id', $this->product->id)->findOrFail($policyId);
        $policy->update(['is_active' => false]);
        $policy->inspections()
            ->whereIn('status', [InspectionStatus::Pending, InspectionStatus::InProgress])
            ->get()
            ->each(fn ($inspection) => (new CancelAssetInspection)($inspection));
        $this->product->refresh();
    }

    public function resetPolicyForm(): void
    {
        $this->reset(['editingPolicyId', 'name', 'description']);
        $this->intervalMonths = 12;
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'policies' => $this->product->inspectionPolicies()->orderBy('name')->get(),
            'summary' => app(AssetInspectionComplianceReader::class)->summarizeProduct($this->product),
        ];
    }
}; ?>

<section class="w-full">
    @include('livewire.products.partials.product-header', ['product' => $product, 'subpage' => 'Inspections'])
    @include('livewire.products.partials.product-tabs', ['product' => $product, 'activeTab' => 'inspections'])

    <div class="space-y-6 px-6 py-4 max-md:px-5 max-sm:px-3">
        <x-signals.panel title="Compliance Summary">
            <div class="grid grid-cols-5 gap-3 max-lg:grid-cols-3 max-sm:grid-cols-2">
                @foreach([
                    'valid' => 'Valid',
                    'due_soon' => 'Due Soon',
                    'overdue' => 'Overdue',
                    'failed' => 'Failed',
                    'unknown' => 'Unknown',
                ] as $key => $label)
                    <div class="rounded-lg border border-[var(--card-border)] px-4 py-3">
                        <div class="text-2xl font-bold text-[var(--text-primary)]">{{ $summary[$key] ?? 0 }}</div>
                        <div class="mt-1 text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">{{ $label }}</div>
                    </div>
                @endforeach
            </div>
        </x-signals.panel>

        <x-signals.panel title="Inspection Policies">
            <form wire:submit="savePolicy" class="grid gap-4 md:grid-cols-4">
                <flux:input wire:model="name" label="Name" placeholder="Annual lifting inspection" />
                <flux:input wire:model="intervalMonths" type="number" min="1" label="Interval (months)" />
                <flux:input wire:model="description" label="Description" />
                <div class="flex items-end gap-2">
                    <flux:button type="submit" variant="primary">{{ $editingPolicyId ? 'Save policy' : 'Add policy' }}</flux:button>
                    @if($editingPolicyId)
                        <flux:button type="button" wire:click="resetPolicyForm">Cancel</flux:button>
                    @endif
                </div>
            </form>

            <div class="mt-6 s-table-wrap">
                <table class="s-table w-full">
                    <thead><tr><th>Name</th><th>Interval</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse($policies as $policy)
                            <tr wire:key="inspection-policy-{{ $policy->id }}">
                                <td>{{ $policy->name }}</td>
                                <td>{{ $policy->interval_months }} months</td>
                                <td>{{ $policy->is_active ? 'Active' : 'Inactive' }}</td>
                                <td class="flex gap-2">
                                    <flux:button size="sm" wire:click="editPolicy({{ $policy->id }})">Edit</flux:button>
                                    @if($policy->is_active)
                                        <flux:button size="sm" wire:click="deactivatePolicy({{ $policy->id }})">Deactivate</flux:button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-[var(--text-muted)]">No inspection policies recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-signals.panel>
    </div>
</section>
