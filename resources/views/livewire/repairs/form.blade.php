<?php

use App\Actions\Repairs\CreateRepair;
use App\Actions\Repairs\UpdateRepair;
use App\Enums\RepairStatus;
use App\Models\Asset;
use App\Models\Product;
use App\Models\Repair;
use App\Models\Store;
use App\Models\Member;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    public ?Repair $repair = null;

    public ?int $product_id = null;
    public ?int $store_id = null;
    public ?int $asset_id = null;
    public int $quantity = 1;
    public string $status = 'open';
    public string $reported_at = '';
    public string $started_at = '';
    public string $expected_completed_at = '';
    public bool $is_usable = true;
    public ?int $assigned_to_user_id = null;
    public ?int $external_repairer_id = null;
    public string $description = '';
    public string $notes = '';

    public function mount(?Repair $repair = null): void
    {
        $this->repair = $repair;
        Gate::authorize($repair?->exists ? 'update' : 'create', $repair ?? Repair::class);

        if ($repair?->exists) {
            $repair->load('asset.currentStockLevel');
            $this->fill([
                'product_id' => $repair->product_id,
                'store_id' => $repair->store_id,
                'asset_id' => $repair->asset_id,
                'quantity' => $repair->quantity,
                'status' => $repair->status->value,
                'reported_at' => $this->inputDate($repair->reported_at),
                'started_at' => $this->inputDate($repair->started_at),
                'expected_completed_at' => $this->inputDate($repair->expected_completed_at),
                'is_usable' => $repair->is_usable,
                'assigned_to_user_id' => $repair->assigned_to_user_id,
                'external_repairer_id' => $repair->external_repairer_id,
                'description' => $repair->description ?? '',
                'notes' => $repair->notes ?? '',
            ]);
        } else {
            $this->reported_at = now()->format('Y-m-d\TH:i');
            $this->asset_id = request()->integer('asset_id') ?: null;
            if ($this->asset_id !== null) {
                $asset = Asset::query()->with('currentStockLevel')->find($this->asset_id);
                $this->product_id = $asset?->product_id;
                $this->store_id = $asset?->currentStockLevel?->store_id;
            }
        }
    }

    public function save(): void
    {
        $this->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'asset_id' => ['nullable', 'integer', 'exists:assets,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:open,in_progress,completed,unrepairable'],
            'reported_at' => ['required', 'date'],
            'started_at' => ['nullable', 'date'],
            'expected_completed_at' => ['nullable', 'date'],
            'is_usable' => ['boolean'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'external_repairer_id' => ['nullable', 'integer', 'exists:members,id'],
            'description' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $attributes = [
            'product_id' => $this->product_id,
            'store_id' => $this->store_id,
            'asset_id' => $this->asset_id,
            'quantity' => $this->quantity,
            'status' => $this->status,
            'reported_at' => Carbon::parse($this->reported_at),
            'started_at' => $this->started_at !== '' ? Carbon::parse($this->started_at) : null,
            'expected_completed_at' => $this->expected_completed_at !== '' ? Carbon::parse($this->expected_completed_at) : null,
            'is_usable' => $this->is_usable,
            'assigned_to_user_id' => $this->assigned_to_user_id,
            'external_repairer_id' => $this->external_repairer_id,
            'description' => $this->description ?: null,
            'notes' => $this->notes ?: null,
        ];

        $repair = $this->repair?->exists
            ? (new UpdateRepair)($this->repair, $attributes)
            : (new CreateRepair)($attributes);

        $this->redirect(route('repairs.show', $repair), navigate: true);
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'stock_method']),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
            'assets' => Asset::query()->with(['product', 'currentStockLevel'])->orderBy('id')->get(),
            'members' => Member::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => RepairStatus::cases(),
        ];
    }

    private function inputDate(?Carbon $date): string
    {
        return $date?->format('Y-m-d\TH:i') ?? '';
    }
}; ?>

<section class="w-full max-w-3xl">
    <x-dense::page-header :title="$repair?->exists ? 'Edit Repair' : 'New Repair'">
        <x-slot:breadcrumbs><a href="{{ route('repairs.index') }}" wire:navigate.hover class="text-[var(--link)] hover:underline">Repairs</a></x-slot:breadcrumbs>
    </x-dense::page-header>

    <form wire:submit="save" class="space-y-4 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select wire:model="product_id" label="Product" required>
                <option value="">Select product</option>
                @foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach
            </flux:select>
            <flux:select wire:model="store_id" label="Store" required>
                <option value="">Select store</option>
                @foreach($stores as $store)<option value="{{ $store->id }}">{{ $store->name }}</option>@endforeach
            </flux:select>
        </div>
        <flux:select wire:model="asset_id" label="Serialized Asset (optional)">
            <option value="">Bulk repair</option>
            @foreach($assets as $asset)<option value="{{ $asset->id }}">{{ $asset->product?->name }} — {{ $asset->currentStockLevel?->asset_number ?? 'Asset #'.$asset->id }}</option>@endforeach
        </flux:select>
        <flux:input wire:model="quantity" type="number" min="1" label="Quantity" required />
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select wire:model="status" label="Status" required>
                @foreach($statuses as $statusOption)<option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>@endforeach
            </flux:select>
            <flux:checkbox wire:model="is_usable" label="Equipment remains usable" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="reported_at" type="datetime-local" label="Reported at" required />
            <flux:input wire:model="started_at" type="datetime-local" label="Started at" />
            <flux:input wire:model="expected_completed_at" type="datetime-local" label="Expected completion" />
            <flux:select wire:model="external_repairer_id" label="External repairer (optional)">
                <option value="">None</option>
                @foreach($members as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach
            </flux:select>
        </div>
        <flux:textarea wire:model="description" label="Description" rows="3" />
        <flux:textarea wire:model="notes" label="Notes" rows="3" />
        <div class="flex gap-2"><flux:button type="submit" variant="primary">Save repair</flux:button><a href="{{ route('repairs.index') }}" wire:navigate.hover class="s-btn s-btn-secondary">Cancel</a></div>
    </form>
</section>
