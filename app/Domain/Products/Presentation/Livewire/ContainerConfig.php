<?php

declare(strict_types=1);

namespace App\Domain\Products\Presentation\Livewire;

use App\Domain\Products\Actions\UpdateProductContainerConfig;
use App\Domain\Products\Data\UpdateProductContainerConfigData;
use App\Domain\Products\Enums\KitComponentBinding;
use App\Domain\Products\Models\Product;
use App\Domain\Stock\Enums\StockMethod;
use App\Domain\Warehouse\Containers\Enums\ContainerAvailabilityMode;
use App\Domain\Warehouse\Containers\Enums\ContainerCheckinMode;
use App\Domain\Warehouse\Containers\Models\Container;
use App\Services\Api\RansackFilter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')] final class ContainerConfig extends Component
{
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
                    'product_name' => $slotProduct === null ? 'Unknown product' : $slotProduct->name,
                    'quantity' => (int) $slot['quantity'],
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

    public function render(): View
    {
        return view('products::container-config');
    }
}
