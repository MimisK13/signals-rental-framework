<?php

use App\Actions\Products\UpdateProductContainerConfig;
use App\Data\Products\UpdateProductContainerConfigData;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\ContainerCheckinMode;
use App\Enums\KitComponentBinding;
use App\Enums\StockMethod;
use App\Models\Container;
use App\Models\Product;
use App\Services\Api\RansackFilter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Product $product;

    public bool $isContainerable = false;

    public string $availabilityMode = 'transport';

    public ?string $checkinMode = null;

    public int $maxNestingDepth = 2;

    public bool $repackOnReturn = false;

    public bool $isWarehouseAddable = false;

    public ?int $warehouseAddDefaultCharge = null;

    /** @var list<array{product_id: int|null, product_name: string, quantity: int, binding: string}> */
    public array $templateSlots = [];

    public string $productSearch = '';

    public function mount(Product $product): void
    {
        abort_unless($product->stock_method === StockMethod::Serialised, 404);

        $this->product = $product->loadCount(['stockLevels', 'accessories', 'attachments']);

        $this->isContainerable = (bool) $product->is_containerable;
        $this->availabilityMode = $product->containerMode()->value;
        $this->checkinMode = $product->container_checkin_mode?->value;
        $this->maxNestingDepth = $product->container_max_nesting_depth ?? 2;
        $this->repackOnReturn = (bool) $product->container_repack_on_return;
        $this->isWarehouseAddable = (bool) $product->is_warehouse_addable;
        $this->warehouseAddDefaultCharge = $product->warehouse_add_default_charge;

        $slots = $product->container_template['slots'] ?? [];
        $this->templateSlots = collect($slots)
            ->map(function (array $slot): array {
                $slotProduct = Product::query()->find($slot['product_id']);

                return [
                    'product_id' => (int) $slot['product_id'],
                    'product_name' => $slotProduct?->name ?? 'Unknown product',
                    'quantity' => (int) ($slot['quantity'] ?? 1),
                    'binding' => (string) ($slot['binding'] ?? KitComponentBinding::Pool->value),
                ];
            })
            ->values()
            ->all();
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name.' — Container Config');
    }

    public function updatedIsContainerable(bool $value): void
    {
        if (! $value) {
            $this->templateSlots = [];
        }
    }

    public function updatedAvailabilityMode(string $value): void
    {
        if ($value === ContainerAvailabilityMode::Transport->value) {
            $this->checkinMode = null;
        }

        foreach ($this->templateSlots as $index => $slot) {
            $this->templateSlots[$index]['binding'] = match ($value) {
                ContainerAvailabilityMode::Kit->value => KitComponentBinding::Fixed->value,
                ContainerAvailabilityMode::Transport->value => KitComponentBinding::Pool->value,
                default => $slot['binding'],
            };
        }
    }

    public function addTemplateSlot(): void
    {
        $defaultBinding = match ($this->availabilityMode) {
            ContainerAvailabilityMode::Kit->value => KitComponentBinding::Fixed->value,
            ContainerAvailabilityMode::Transport->value => KitComponentBinding::Pool->value,
            default => KitComponentBinding::Pool->value,
        };

        $this->templateSlots[] = [
            'product_id' => null,
            'product_name' => '',
            'quantity' => 1,
            'binding' => $defaultBinding,
        ];
    }

    public function removeTemplateSlot(int $index): void
    {
        unset($this->templateSlots[$index]);
        $this->templateSlots = array_values($this->templateSlots);
    }

    public function selectSlotProduct(int $index, int $productId, string $productName): void
    {
        if (! isset($this->templateSlots[$index])) {
            return;
        }

        $this->templateSlots[$index]['product_id'] = $productId;
        $this->templateSlots[$index]['product_name'] = $productName;
        $this->productSearch = '';
    }

    public function save(): void
    {
        Gate::authorize('manageTemplates', Container::class);

        $template = null;

        if ($this->isContainerable && $this->templateSlots !== []) {
            $template = [
                'slots' => collect($this->templateSlots)
                    ->filter(fn (array $slot): bool => $slot['product_id'] !== null)
                    ->map(fn (array $slot): array => [
                        'product_id' => (int) $slot['product_id'],
                        'quantity' => (int) $slot['quantity'],
                        'binding' => $slot['binding'],
                    ])
                    ->values()
                    ->all(),
            ];
        }

        $mode = ContainerAvailabilityMode::from($this->availabilityMode);

        (new UpdateProductContainerConfig)($this->product, UpdateProductContainerConfigData::from([
            'is_containerable' => $this->isContainerable,
            'container_availability_mode' => $this->isContainerable ? $mode : null,
            'container_checkin_mode' => $this->isContainerable && $this->checkinMode
                ? ContainerCheckinMode::from($this->checkinMode)
                : null,
            'container_max_nesting_depth' => $this->maxNestingDepth,
            'container_repack_on_return' => $this->repackOnReturn,
            'is_warehouse_addable' => $this->isWarehouseAddable,
            'warehouse_add_default_charge' => $this->warehouseAddDefaultCharge,
            'container_template' => $template,
        ]));

        $this->product->refresh();
        $this->dispatch('container-config-saved');
    }

    /**
     * @return Collection<int, Product>
     */
    #[Computed]
    public function productSearchResults(): Collection
    {
        if (strlen($this->productSearch) < 1) {
            return new Collection;
        }

        return Product::query()
            ->where('stock_method', StockMethod::Serialised)
            ->where('id', '!=', $this->product->id)
            ->where('name', 'ilike', '%'.RansackFilter::escapeLike($this->productSearch).'%')
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name']);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'canManage' => auth()->user()?->can('containers.manage_templates') ?? false,
            'availabilityModes' => ContainerAvailabilityMode::cases(),
            'checkinModes' => ContainerCheckinMode::cases(),
            'bindings' => KitComponentBinding::cases(),
            'isHybrid' => $this->availabilityMode === ContainerAvailabilityMode::Hybrid->value,
            'showCheckinMode' => in_array($this->availabilityMode, [
                ContainerAvailabilityMode::Kit->value,
                ContainerAvailabilityMode::Hybrid->value,
            ], true),
        ];
    }
}; ?>

<section class="w-full">
    @include('livewire.products.partials.product-header', ['product' => $product])

    @include('livewire.products.partials.product-tabs', ['product' => $product, 'activeTab' => 'container-config'])

    <div class="px-6 py-4 max-md:px-5 max-sm:px-3">
        <x-signals.panel title="Container Configuration">
            @unless($canManage)
                <flux:callout variant="warning" class="mb-4">
                    You can view container settings but need the Manage Container Templates permission to edit them.
                </flux:callout>
            @endunless

            <form wire:submit="save" class="space-y-6">
                <flux:switch wire:model.live="isContainerable" label="Containerable" :disabled="! $canManage" />

                @if($isContainerable)
                    <div class="grid grid-cols-2 gap-4 max-md:grid-cols-1">
                        <flux:select wire:model.live="availabilityMode" label="Availability Mode" :disabled="! $canManage">
                            @foreach($availabilityModes as $mode)
                                <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                            @endforeach
                        </flux:select>

                        @if($showCheckinMode)
                            <flux:select wire:model="checkinMode" label="Check-in Mode" :disabled="! $canManage">
                                <option value="">Default (parent)</option>
                                @foreach($checkinModes as $mode)
                                    <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                                @endforeach
                            </flux:select>
                        @endif

                        <flux:input wire:model.number="maxNestingDepth" type="number" min="1" max="3" label="Max Nesting Depth" :disabled="! $canManage" />
                        <flux:switch wire:model="repackOnReturn" label="Repack on Return" :disabled="! $canManage" />
                        <flux:switch wire:model="isWarehouseAddable" label="Warehouse Addable at Dispatch" :disabled="! $canManage" />
                        <flux:input wire:model.number="warehouseAddDefaultCharge" type="number" min="0" label="Warehouse Add Default Charge (minor units)" :disabled="! $canManage" />
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-medium text-(--text-secondary)">Template Slots</h3>
                            @if($canManage)
                                <flux:button type="button" size="sm" wire:click="addTemplateSlot">Add Slot</flux:button>
                            @endif
                        </div>

                        @forelse($templateSlots as $index => $slot)
                            <div class="grid grid-cols-[1fr_120px_120px_auto] gap-3 items-end max-lg:grid-cols-1" wire:key="template-slot-{{ $index }}">
                                <div>
                                    @if($slot['product_id'])
                                        <flux:input label="Product" value="{{ $slot['product_name'] }}" readonly />
                                    @else
                                        <flux:input wire:model.live.debounce.300ms="productSearch" label="Product" placeholder="Search serialised products…" :disabled="! $canManage" />
                                        @if($productSearch !== '' && $productSearchResults->isNotEmpty())
                                            <ul class="mt-1 rounded border border-(--border-subtle) bg-(--surface-primary) shadow-sm">
                                                @foreach($productSearchResults as $result)
                                                    <li>
                                                        <button type="button" class="w-full px-3 py-2 text-left text-sm hover:bg-(--surface-secondary)" wire:click="selectSlotProduct({{ $index }}, {{ $result->id }}, @js($result->name))">
                                                            {{ $result->name }}
                                                        </button>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    @endif
                                </div>
                                <flux:input wire:model.number="templateSlots.{{ $index }}.quantity" type="number" min="1" label="Qty" :disabled="! $canManage" />
                                @if($isHybrid)
                                    <flux:select wire:model="templateSlots.{{ $index }}.binding" label="Binding" :disabled="! $canManage">
                                        @foreach($bindings as $binding)
                                            <option value="{{ $binding->value }}">{{ $binding->label() }}</option>
                                        @endforeach
                                    </flux:select>
                                @else
                                    <flux:input label="Binding" value="{{ ucfirst($slot['binding']) }}" readonly />
                                @endif
                                @if($canManage)
                                    <flux:button type="button" variant="danger" size="sm" wire:click="removeTemplateSlot({{ $index }})">Remove</flux:button>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-(--text-muted)">No template slots defined. Add slots to define the strict packing specification.</p>
                        @endforelse
                    </div>
                @endif

                @if($canManage)
                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="save">Save Container Config</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </flux:button>
                    </div>
                @endif
            </form>
        </x-signals.panel>
    </div>
</section>
