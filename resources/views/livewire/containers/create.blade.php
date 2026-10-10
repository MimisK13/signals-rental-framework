<?php

use App\Actions\Containers\CreateDurableContainer;
use App\Data\Containers\CreateDurableContainerData;
use App\Enums\StockMethod;
use App\Models\Container;
use App\Models\Product;
use App\Models\Store;
use App\Services\Api\RansackFilter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component
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
}; ?>

<section class="w-full" data-density="compact">
    <x-dense::page-header title="New Durable Container">
        <x-slot:breadcrumbs>
            <a href="{{ route('containers.index') }}" wire:navigate.hover class="text-[var(--link)] hover:underline">Containers</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>New Durable Container</span>
        </x-slot:breadcrumbs>
        <x-slot:meta>
            <span>Register a reusable physical Container in inventory</span>
        </x-slot:meta>
    </x-dense::page-header>

    <div class="w-full px-6 py-4 max-md:px-5 max-sm:px-3">
        @if($errors->any())
            <x-signals.alert type="danger" title="Check the highlighted fields" class="mb-4">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-signals.alert>
        @endif

        <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
            <form wire:submit="create" class="space-y-3">
                <div class="s-card shadow-none">
                    <div class="s-card-header">
                        <h2 class="s-card-title">Details</h2>
                    </div>
                    <div class="s-card-body space-y-4">
                    <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        @if($productId)
                            <x-dense::field-label id="durable-product-label" required>Product</x-dense::field-label>
                        @else
                            <x-dense::field-label for="durable-product-search" required>Product</x-dense::field-label>
                        @endif

                        @if($productId)
                            <div class="flex min-h-[var(--erp-control-height,40px)] items-center justify-between gap-3 rounded-md border border-[var(--s-border-sub)] bg-[var(--s-subtle)] px-3 py-2" role="group" aria-labelledby="durable-product-label">
                                <span class="font-medium text-[var(--text-primary)]">{{ $selectedProductName }}</span>
                                <x-dense::button type="button" variant="ghost" size="sm" wire:click="clearProduct">Change</x-dense::button>
                            </div>
                        @else
                            <x-dense::search-input id="durable-product-search" model="productSearch" placeholder="Search container Product by name, SKU, or barcode" class="w-full" autocomplete="off">
                                @if($productResults !== [])
                                    <div class="absolute left-0 right-0 top-full z-30 mt-1 overflow-hidden rounded-md border border-[var(--card-border)] bg-[var(--card-bg)] shadow-[var(--dropdown-shadow)]" role="listbox" aria-label="Container Products">
                                        @foreach($productResults as $product)
                                            <button type="button" wire:key="durable-product-{{ $product['id'] }}" wire:click="selectProduct({{ $product['id'] }})" class="flex w-full items-center justify-between gap-3 border-b border-[var(--s-border-sub)] px-3 py-2 text-left text-sm last:border-b-0 hover:bg-[var(--s-accent-light)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-[var(--blue-ink)]">
                                                <span class="font-medium text-[var(--text-primary)]">{{ $product['name'] }}</span>
                                                <span class="shrink-0 font-mono text-xs text-[var(--text-muted)]">{{ $product['sku'] ?? $product['barcode'] ?? 'Container Product' }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </x-dense::search-input>
                        @endif
                        @error('productId')<p class="mt-1 text-sm text-[var(--danger)]" role="alert">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        @if($storeId)
                            <x-dense::field-label id="durable-store-label" required>Store</x-dense::field-label>
                        @else
                            <x-dense::field-label for="durable-store-search" required>Store</x-dense::field-label>
                        @endif
                        @if($storeId)
                            <div class="box-border flex h-[var(--erp-control-height,40px)] items-center justify-between gap-3 border border-[var(--erp-color-border)] bg-[var(--erp-color-surface)] px-3" role="group" aria-labelledby="durable-store-label">
                                <span class="font-medium text-[var(--text-primary)]">{{ $selectedStoreName }}</span>
                                <x-dense::button type="button" variant="ghost" size="sm" wire:click="clearStore">Change</x-dense::button>
                            </div>
                        @else
                            <x-dense::search-input id="durable-store-search" model="storeSearch" placeholder="Search Store by name" class="w-full" autocomplete="off">
                                @if($storeResults !== [])
                                    <div class="absolute left-0 right-0 top-full z-30 mt-1 overflow-hidden rounded-md border border-[var(--card-border)] bg-[var(--card-bg)] shadow-[var(--dropdown-shadow)]" role="listbox" aria-label="Stores">
                                        @foreach($storeResults as $store)
                                            <button type="button" wire:key="durable-store-{{ $store['id'] }}" wire:click="selectStore({{ $store['id'] }})" class="flex w-full items-center border-b border-[var(--s-border-sub)] px-3 py-2 text-left text-sm last:border-b-0 hover:bg-[var(--s-accent-light)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-[var(--blue-ink)]">
                                                <span class="font-medium text-[var(--text-primary)]">{{ $store['name'] }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </x-dense::search-input>
                        @endif
                        @error('storeId')<p id="durable-store-error" class="dense-field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <x-dense::field-label for="durable-asset-number" required>Asset Number</x-dense::field-label>
                            <x-dense::input id="durable-asset-number" wire:model="assetNumber" placeholder="FC-001" required autocomplete="off" aria-describedby="durable-asset-number-error" />
                            @error('assetNumber')<p id="durable-asset-number-error" class="dense-field-error" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <x-dense::field-label for="durable-serial-number">Serial Number</x-dense::field-label>
                            <x-dense::input id="durable-serial-number" wire:model="serialNumber" placeholder="Optional" autocomplete="off" aria-describedby="durable-serial-number-error" />
                            @error('serialNumber')<p id="durable-serial-number-error" class="dense-field-error" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <x-dense::field-label for="durable-barcode">Barcode</x-dense::field-label>
                            <x-dense::input id="durable-barcode" wire:model="barcode" placeholder="Optional" autocomplete="off" aria-describedby="durable-barcode-error" />
                            @error('barcode')<p id="durable-barcode-error" class="dense-field-error" role="alert">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <x-dense::field-label for="durable-container-name">Container Name</x-dense::field-label>
                        <x-dense::input id="durable-container-name" wire:model="name" placeholder="Example: FOH M32 Case" autocomplete="off" aria-describedby="durable-container-name-error" />
                        @error('name')<p id="durable-container-name-error" class="dense-field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    </div>

                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-[var(--card-border)] px-4 py-3">
                    <x-dense::button href="{{ route('containers.index') }}" wire:navigate.hover variant="ghost" size="base">Cancel</x-dense::button>
                    <x-dense::button type="submit" variant="primary" size="base" wire:loading.attr="disabled" wire:target="create">
                        <span wire:loading.remove wire:target="create">Create Container</span>
                        <span wire:loading wire:target="create">Creating…</span>
                    </x-dense::button>
                </div>
                </div>
            </form>

            <x-dense::card title="Workflow" class="shadow-none">
                <div class="flex flex-col">
                    <p class="mb-4 text-justify text-xs leading-5 text-[var(--text-secondary)]">
                        Register the reusable physical Container here. Add its contents afterward from the Pack workspace.
                    </p>

                    <div class="s-timeline flex flex-col">
                    <div class="s-timeline-item flex-1">
                        <div class="s-timeline-line"></div>
                        <span class="s-timeline-dot s-timeline-dot-blue"></span>
                        <div class="s-timeline-content">
                            <p class="s-timeline-title">Choose Product and Store</p>
                            <p class="s-timeline-body text-justify">Product is the model of the physical Container, such as an M32 Flightcase or Cable Trunk. Store is where this unit is registered.</p>
                        </div>
                    </div>
                    <div class="s-timeline-item flex-1">
                        <div class="s-timeline-line"></div>
                        <span class="s-timeline-dot"></span>
                        <div class="s-timeline-content">
                            <p class="s-timeline-title">Enter physical identity</p>
                            <p class="s-timeline-body text-justify">Asset Number is the required internal identifier. Serial Number is the manufacturer serial, if available. Barcode is the code used to scan this Container.</p>
                        </div>
                    </div>
                    <div class="s-timeline-item flex-1">
                        <div class="s-timeline-line"></div>
                        <span class="s-timeline-dot"></span>
                        <div class="s-timeline-content">
                            <p class="s-timeline-title">Create the identity</p>
                            <p class="s-timeline-body text-justify">Signals creates the physical inventory identity: Asset, serialized StockLevel with quantity 1, and its opening transaction. Container Name is the human-friendly name and may be left blank.</p>
                        </div>
                    </div>
                    <div class="s-timeline-item flex-1">
                        <span class="s-timeline-dot"></span>
                        <div class="s-timeline-content">
                            <p class="s-timeline-title">Pack the Container</p>
                            <p class="s-timeline-body text-justify">After creation, continue to the existing Pack workspace to add the equipment inside. Creating this Container does not create its contents.</p>
                        </div>
                    </div>
                    </div>

                </div>
            </x-dense::card>
        </div>
    </div>
</section>
