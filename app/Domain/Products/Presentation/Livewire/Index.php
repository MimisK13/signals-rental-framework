<?php

declare(strict_types=1);

namespace App\Domain\Products\Presentation\Livewire;

use App\Domain\Products\Actions\ArchiveProduct;
use App\Domain\Products\Actions\DeleteProduct;
use App\Domain\Products\Actions\RestoreProduct;
use App\Domain\Products\Enums\ProductType;
use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductGroup;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Products')] final class Index extends Component
{
    #[Url(as: 'type')]
    public string $typeFilter = '';

    #[Url(as: 'archive')]
    public string $archiveFilter = 'active';

    /** @var Collection<string, int> */
    public Collection $typeCounts;

    public int $totalCount = 0;

    /** @var array<int|string, string>|null */
    private ?array $groupOptionsCache = null;

    public function mount(): void
    {
        $type = request()->query('type', '');
        if ($type !== '' && ProductType::tryFrom($type)) {
            $this->typeFilter = $type;
        }

        $this->refreshTypeCounts();
    }

    public function setTypeFilter(string $type): void
    {
        if ($type !== '' && ProductType::tryFrom($type) === null) {
            return;
        }

        $this->typeFilter = $type;
    }

    public function archiveProduct(int $productId): void
    {
        $product = Product::findOrFail($productId);
        (new ArchiveProduct)($product);
        $this->refreshTypeCounts();
        $this->dispatch('product-archived');
    }

    public function restoreProduct(int $productId): void
    {
        $product = Product::withTrashed()->findOrFail($productId);
        (new RestoreProduct)($product);
        $this->refreshTypeCounts();
        $this->dispatch('product-restored');
    }

    /**
     * @param  array<int, int>  $ids
     */
    public function archiveSelected(array $ids): void
    {
        $action = new DeleteProduct;
        foreach ($ids as $id) {
            $product = Product::find($id);
            if ($product) {
                $action($product);
            }
        }
        $this->refreshTypeCounts();
        $this->dispatch('product-archived');
    }

    #[On('product-archived')]
    #[On('product-restored')]
    public function refreshTypeCounts(): void
    {
        $query = match ($this->archiveFilter) {
            'archived' => Product::onlyTrashed(),
            'all' => Product::withTrashed(),
            default => Product::query(),
        };

        $this->typeCounts = $query
            ->selectRaw('product_type, count(*) as count')
            ->groupBy('product_type')
            ->pluck('count', 'product_type');

        $this->totalCount = $this->typeCounts->sum();
    }

    public function setArchiveFilter(string $filter): void
    {
        if (! in_array($filter, ['active', 'archived', 'all'])) {
            return;
        }

        $this->archiveFilter = $filter;
        $this->refreshTypeCounts();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $typeOptions = collect(ProductType::cases())
            ->mapWithKeys(fn (ProductType $t): array => [$t->value => $t->label()])
            ->all();

        $groupOptions = $this->groupOptionsCache ??= ProductGroup::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return [
            'productTypes' => [
                ProductType::Rental,
                ProductType::Sale,
                ProductType::Service,
            ],
            'totalCount' => $this->totalCount,
            'typeCounts' => $this->typeCounts,
            'columns' => [
                ['key' => 'checkbox', 'type' => 'checkbox'],
                ['key' => 'name', 'label' => 'Name', 'sortable' => true, 'filterable' => true, 'filter_type' => 'text', 'view' => 'products::partials.column-name'],
                ['key' => 'product_type', 'label' => 'Type', 'sortable' => true, 'filterable' => true, 'filter_type' => 'select', 'filter_options' => $typeOptions, 'view' => 'products::partials.column-type'],
                ['key' => 'product_group_id', 'label' => 'Group', 'sortable' => true, 'filterable' => true, 'filter_type' => 'select', 'filter_options' => $groupOptions, 'view' => 'products::partials.column-group'],
                ['key' => 'sku', 'label' => 'SKU', 'sortable' => true, 'view' => 'products::partials.column-sku'],
                ['key' => 'stock_levels_count', 'label' => 'Stock', 'sortable' => true],
                ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'filterable' => true, 'filter_type' => 'select', 'filter_options' => ['1' => 'Active', '0' => 'Inactive'], 'view' => 'livewire.partials.column-active-status'],
                ['key' => 'created_at', 'label' => 'Created', 'sortable' => true],
                ['key' => 'updated_at', 'label' => 'Updated', 'sortable' => true],
                ['key' => 'actions', 'type' => 'actions'],
            ],
            'scopes' => [
                ...($this->typeFilter !== '' ? ['ofType' => ProductType::from($this->typeFilter)] : []),
                ...match ($this->archiveFilter) {
                    'archived' => ['archived' => true],
                    'all' => ['withArchived' => true],
                    default => [],
                },
            ],
        ];
    }

    public function render(): View
    {
        return view('products::index');
    }
}
