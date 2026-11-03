<?php

declare(strict_types=1);

namespace App\Domain\Assets\Presentation\Livewire;

use App\Domain\Assets\Enums\InspectionStatus;
use App\Domain\Assets\InspectionActions\CancelAssetInspection;
use App\Domain\Assets\Models\InspectionPolicy;
use App\Domain\Assets\Services\AssetInspectionComplianceReader;
use App\Domain\Products\Models\Product;
use App\Domain\Stock\Enums\StockMethod;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class ProductInspections extends Component
{
    public Product $product;

    public ?int $editingPolicyId = null;

    public string $name = '';

    public string $description = '';

    public int $intervalMonths = 12;

    public function mount(Product $product): void
    {
        Gate::authorize('stock.view');
        abort_unless($product->stock_method === StockMethod::Serialised, 404);
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

    public function render(): View
    {
        return view('assets::product-inspections');
    }
}
