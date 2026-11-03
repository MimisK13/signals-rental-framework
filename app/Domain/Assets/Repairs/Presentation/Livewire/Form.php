<?php

declare(strict_types=1);

namespace App\Domain\Assets\Repairs\Presentation\Livewire;

use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Repairs\Actions\CreateRepair;
use App\Domain\Assets\Repairs\Actions\UpdateRepair;
use App\Domain\Assets\Repairs\Enums\RepairStatus;
use App\Domain\Assets\Repairs\Models\Repair;
use App\Models\Member;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Form extends Component
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

    public function render(): View
    {
        return view('repairs::form', $this->with());
    }
}
