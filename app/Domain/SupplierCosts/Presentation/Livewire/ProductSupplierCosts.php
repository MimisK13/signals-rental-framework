<?php

declare(strict_types=1);

namespace App\Domain\SupplierCosts\Presentation\Livewire;

use App\Domain\Currency\Models\Currency;
use App\Domain\Products\Models\Product;
use App\Domain\SupplierCosts\Actions\CreateSupplierCost;
use App\Domain\SupplierCosts\Actions\DeleteSupplierCost;
use App\Domain\SupplierCosts\Actions\SetPreferredSupplier;
use App\Domain\SupplierCosts\Actions\UpdateSupplierCost;
use App\Domain\SupplierCosts\Data\CreateSupplierCostData;
use App\Domain\SupplierCosts\Data\UpdateSupplierCostData;
use App\Domain\SupplierCosts\Models\ProductSupplierCost;
use App\Domain\SupplierCosts\Rules\EnsureSupplierCostCurrencyIsValid;
use App\Enums\MembershipType;
use App\Models\Member;
use App\Models\Store;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class ProductSupplierCosts extends Component
{
    public Product $product;

    public bool $showFormModal = false;

    public ?int $editingCostId = null;

    public ?int $supplierId = null;

    public ?int $storeId = null;

    public string $costBasis = 'per_unit';

    public string $cost = '';

    public string $currencyCode = 'GBP';

    public ?string $supplierSku = null;

    public ?int $leadTimeDays = null;

    public string $minOrderQuantity = '1.00';

    public bool $isPreferred = false;

    public function mount(Product $product): void
    {
        Gate::authorize('viewAny', ProductSupplierCost::class);

        $this->product = $product;
        $this->currencyCode = (string) (settings('company.base_currency') ?? 'GBP');
        $this->loadCosts();
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name.' — Supplier Costs');
    }

    public function openCreateModal(): void
    {
        Gate::authorize('create', ProductSupplierCost::class);

        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEditModal(int $costId): void
    {
        $cost = $this->product->supplierCosts()->findOrFail($costId);
        Gate::authorize('update', $cost);

        $this->editingCostId = $cost->id;
        $this->supplierId = $cost->supplier_id;
        $this->storeId = $cost->store_id;
        $this->costBasis = $cost->cost_basis;
        $this->cost = (string) Money::ofMinor($cost->cost, $cost->currency_code)->getAmount();
        $this->currencyCode = $cost->currency_code;
        $this->supplierSku = $cost->supplier_sku;
        $this->leadTimeDays = $cost->lead_time_days;
        $this->minOrderQuantity = bcadd((string) $cost->min_order_quantity, '0', 2);
        $this->isPreferred = $cost->is_preferred;
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $costBases = array_keys(config('supplier_costs.cost_bases', []));

        $this->validate([
            'supplierId' => [
                'required',
                'integer',
                Rule::exists('members', 'id')->where('membership_type', MembershipType::Organisation->value),
            ],
            'storeId' => ['nullable', 'integer', 'exists:stores,id'],
            'costBasis' => ['required', 'string', Rule::in($costBases)],
            'cost' => ['required', 'numeric', 'min:0'],
            'currencyCode' => ['required', 'string', 'size:3'],
            'supplierSku' => ['nullable', 'string', 'max:255'],
            'leadTimeDays' => ['nullable', 'integer', 'min:0'],
            'minOrderQuantity' => ['required', 'numeric', 'min:0.01'],
            'isPreferred' => ['boolean'],
        ]);

        $this->currencyCode = (new EnsureSupplierCostCurrencyIsValid)($this->currencyCode);

        $minor = Money::of($this->cost, $this->currencyCode, roundingMode: RoundingMode::HALF_UP)
            ->getMinorAmount()
            ->toInt();

        if ($this->editingCostId !== null) {
            $cost = $this->product->supplierCosts()->findOrFail($this->editingCostId);

            (new UpdateSupplierCost)($cost, UpdateSupplierCostData::from([
                'supplier_id' => $this->supplierId,
                'store_id' => $this->storeId,
                'store_id_provided' => true,
                'cost_basis' => $this->costBasis,
                'cost' => $minor,
                'currency_code' => $this->currencyCode,
                'supplier_sku' => $this->supplierSku,
                'supplier_sku_provided' => true,
                'lead_time_days' => $this->leadTimeDays,
                'lead_time_days_provided' => true,
                'min_order_quantity' => $this->minOrderQuantity,
                'is_preferred' => $this->isPreferred,
            ]));
        } else {
            (new CreateSupplierCost)(CreateSupplierCostData::from([
                'product_id' => $this->product->id,
                'supplier_id' => $this->supplierId,
                'store_id' => $this->storeId,
                'cost_basis' => $this->costBasis,
                'cost' => $minor,
                'currency_code' => $this->currencyCode,
                'supplier_sku' => $this->supplierSku,
                'lead_time_days' => $this->leadTimeDays,
                'min_order_quantity' => $this->minOrderQuantity,
                'is_preferred' => $this->isPreferred,
            ]));
        }

        $this->showFormModal = false;
        $this->resetForm();
        $this->loadCosts();
        $this->dispatch('supplier-cost-saved');
    }

    public function setPreferred(int $costId): void
    {
        $cost = $this->product->supplierCosts()->findOrFail($costId);

        (new SetPreferredSupplier)($cost);

        $this->loadCosts();
        $this->dispatch('supplier-cost-preferred');
    }

    public function deleteCost(int $costId): void
    {
        $cost = $this->product->supplierCosts()->findOrFail($costId);

        (new DeleteSupplierCost)($cost);

        $this->loadCosts();
        $this->dispatch('supplier-cost-deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(): array
    {
        $costBases = config('supplier_costs.cost_bases', []);

        return [
            'costs' => $this->product->supplierCosts()
                ->with(['supplier', 'store'])
                ->orderByDesc('is_preferred')
                ->orderBy('supplier_id')
                ->get(),
            'suppliers' => Member::query()
                ->where('membership_type', MembershipType::Organisation)
                ->orderBy('name')
                ->get(['id', 'name']),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
            'costBases' => $costBases,
            'currencies' => Currency::query()->enabled()->orderBy('code')->get(['code', 'name']),
            'canManage' => auth()->user()?->can('products.supplier_costs') ?? false,
        ];
    }

    private function resetForm(): void
    {
        $this->editingCostId = null;
        $this->supplierId = null;
        $this->storeId = null;
        $this->costBasis = 'per_unit';
        $this->cost = '';
        $this->currencyCode = (string) (settings('company.base_currency') ?? 'GBP');
        $this->supplierSku = null;
        $this->leadTimeDays = null;
        $this->minOrderQuantity = '1.00';
        $this->isPreferred = false;
        $this->resetValidation();
    }

    private function loadCosts(): void
    {
        $this->product->loadCount(['stockLevels', 'accessories', 'attachments', 'rates', 'supplierCosts', 'activities']);
    }

    public function render(): View
    {
        return view('supplier-costs::product-supplier-costs', $this->viewData());
    }
}
