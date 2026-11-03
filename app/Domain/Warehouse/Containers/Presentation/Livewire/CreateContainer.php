<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Presentation\Livewire;

use App\Domain\Products\Models\Product;
use App\Domain\Stock\Enums\StockMethod;
use App\Domain\Warehouse\Containers\Actions\CreateDurableContainer;
use App\Domain\Warehouse\Containers\Data\CreateDurableContainerData;
use App\Domain\Warehouse\Containers\Models\Container;
use App\Models\Store;
use App\Services\Api\RansackFilter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class CreateContainer extends Component
{
    public ?int $productId = null;

    public ?int $storeId = null;

    public string $assetNumber = '';

    public string $serialNumber = '';

    public string $barcode = '';

    public string $name = '';

    public string $productSearch = '';

    /** @var array<int, array{id: int, name: string, sku: string|null, barcode: string|null}> */
    public array $productResults = [];

    public ?string $selectedProductName = null;

    public string $storeSearch = '';

    /** @var array<int, array{id: int, name: string}> */
    public array $storeResults = [];

    public ?string $selectedStoreName = null;

    public function mount(): void
    {
        Gate::authorize('create', Container::class);

        $stores = $this->stores();

        if ($stores->count() === 1) {
            $this->storeId = $stores->first()->id;
            $this->selectedStoreName = $stores->first()->name;
        }
    }

    public function updatedProductSearch(): void
    {
        $search = trim($this->productSearch);

        if (mb_strlen($search) < 1) {
            $this->productResults = [];

            return;
        }

        $term = '%'.RansackFilter::escapeLike($search).'%';
        $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        // This query narrows the selector for operator usability only. The
        // CreateDurableContainer Action remains authoritative for eligibility.
        $this->productResults = Product::query()
            ->where('is_active', true)
            ->where('is_containerable', true)
            ->where('stock_method', StockMethod::Serialised)
            ->where(function ($query) use ($term, $operator): void {
                $query
                    ->where('name', $operator, $term)
                    ->orWhere('sku', $operator, $term)
                    ->orWhere('barcode', $operator, $term);
            })
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'sku', 'barcode'])
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
            ])
            ->all();
    }

    public function selectProduct(int $productId): void
    {
        $product = Product::query()
            ->where('is_active', true)
            ->where('is_containerable', true)
            ->where('stock_method', StockMethod::Serialised)
            ->find($productId);

        if ($product === null) {
            return;
        }

        $this->productId = $product->id;
        $this->selectedProductName = $product->name;
        $this->productSearch = '';
        $this->productResults = [];
    }

    public function clearProduct(): void
    {
        $this->productId = null;
        $this->selectedProductName = null;
        $this->productSearch = '';
        $this->productResults = [];
    }

    public function updatedStoreSearch(): void
    {
        $search = trim($this->storeSearch);

        if (mb_strlen($search) < 1) {
            $this->storeResults = [];

            return;
        }

        $term = '%'.RansackFilter::escapeLike($search).'%';
        $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $accessibleStoreIds = auth()->user()?->accessibleStoreIds();

        $this->storeResults = Store::query()
            ->when($accessibleStoreIds !== null, fn ($query) => $query->whereIn('id', $accessibleStoreIds))
            ->where('name', $operator, $term)
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name'])
            ->map(fn (Store $store): array => [
                'id' => $store->id,
                'name' => $store->name,
            ])
            ->all();
    }

    public function selectStore(int $storeId): void
    {
        $storeIds = auth()->user()?->accessibleStoreIds();
        $store = Store::query()
            ->when($storeIds !== null, fn ($query) => $query->whereIn('id', $storeIds))
            ->find($storeId);

        if ($store === null) {
            return;
        }

        $this->storeId = $store->id;
        $this->selectedStoreName = $store->name;
        $this->storeSearch = '';
        $this->storeResults = [];
    }

    public function clearStore(): void
    {
        $this->storeId = null;
        $this->selectedStoreName = null;
        $this->storeSearch = '';
        $this->storeResults = [];
    }

    public function create(): void
    {
        $this->validate([
            'productId' => ['required', 'integer', 'exists:products,id'],
            'storeId' => ['required', 'integer', 'exists:stores,id'],
            'assetNumber' => ['required', 'string', 'max:255'],
            'serialNumber' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = app(CreateDurableContainer::class)(CreateDurableContainerData::from([
                'product_id' => $this->productId,
                'store_id' => $this->storeId,
                'asset_number' => trim($this->assetNumber),
                'serial_number' => $this->nullableTrim($this->serialNumber),
                'barcode' => $this->nullableTrim($this->barcode),
                'name' => $this->nullableTrim($this->name),
            ]));
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($this->uiFieldFor($field), $message);
                }
            }

            return;
        }

        $container = Container::query()->findOrFail($result->id);

        session()->flash('container_status', __('Container :name created.', [
            'name' => $container->name,
        ]));

        $destination = Gate::check('pack', $container)
            ? route('containers.pack', $container)
            : route('containers.show', $container);

        $this->redirect($destination, navigate: true);
    }

    /** @return Collection<int, Store> */
    public function stores(): Collection
    {
        $storeIds = auth()->user()?->accessibleStoreIds();

        return Store::query()
            ->when($storeIds !== null, fn ($query) => $query->whereIn('id', $storeIds))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'stores' => $this->stores(),
        ];
    }

    private function nullableTrim(string $value): ?string
    {
        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function uiFieldFor(string $field): string
    {
        return match ($field) {
            'product_id' => 'productId',
            'store_id' => 'storeId',
            'asset_number' => 'assetNumber',
            'serial_number' => 'serialNumber',
            'serialised_item_id' => 'barcode',
            default => $field,
        };
    }

    public function render(): View
    {
        return view('containers::create', $this->with());
    }
}
