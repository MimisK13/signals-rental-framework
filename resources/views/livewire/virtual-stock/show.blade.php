<?php

use App\Actions\VirtualStock\CancelVirtualStockIntake;
use App\Actions\VirtualStock\ConfirmVirtualStockIntake;
use App\Actions\VirtualStock\DeleteDraftVirtualStockIntake;
use App\Actions\VirtualStock\ReceiveVirtualStockIntake;
use App\Actions\VirtualStock\ReturnVirtualStockIntake;
use App\Data\VirtualStock\ReturnVirtualStockIntakeData;
use App\Models\OpportunityCost;
use App\Models\ShortageResolution;
use App\Models\VirtualStockIntake;
use App\Services\Shortages\CostApportionmentRegistry;
use App\Services\VirtualStock\VirtualStockIntakeProfitAndLossBuilder;
use App\Services\VirtualStock\VirtualStockIntakeReturnGuard;
use App\Services\VirtualStock\VirtualStockStatusRegistry;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public VirtualStockIntake $virtualStockIntake;

    public bool $showReturnConfirmModal = false;

    public function mount(VirtualStockIntake $virtualStockIntake): void
    {
        Gate::authorize('view', $virtualStockIntake);
        $this->virtualStockIntake = $virtualStockIntake->load(['product', 'store', 'purchaseOrder', 'purchaseOrderItem']);
    }

    public function confirm(): void
    {
        app(ConfirmVirtualStockIntake::class)($this->virtualStockIntake);
        $this->virtualStockIntake->refresh()->load(['product', 'store']);
    }

    public function receive(): void
    {
        app(ReceiveVirtualStockIntake::class)($this->virtualStockIntake);
        $this->virtualStockIntake->refresh();
    }

    public function openReturnToSupplier(): void
    {
        if (app(VirtualStockIntakeReturnGuard::class)->hasOutstandingAllocations($this->virtualStockIntake)) {
            $this->showReturnConfirmModal = true;

            return;
        }

        $this->returnToSupplier(force: false);
    }

    public function confirmReturnToSupplier(): void
    {
        $this->returnToSupplier(force: true);
        $this->showReturnConfirmModal = false;
    }

    public function returnToSupplier(bool $force = false): void
    {
        app(ReturnVirtualStockIntake::class)(
            $this->virtualStockIntake,
            new ReturnVirtualStockIntakeData(force: $force),
        );
        $this->virtualStockIntake->refresh();
    }

    public function cancel(): void
    {
        app(CancelVirtualStockIntake::class)($this->virtualStockIntake);
        $this->virtualStockIntake->refresh();
    }

    public function deleteDraft(): void
    {
        (new DeleteDraftVirtualStockIntake)($this->virtualStockIntake);
        $this->redirect(route('virtual-stock.index'), navigate: true);
    }

    public function with(): array
    {
        $registry = app(VirtualStockStatusRegistry::class);
        $canViewCosts = Gate::check('costs.view');
        $strategyKey = $this->resolveApportionmentStrategy();
        $strategyName = $canViewCosts
            ? (app(CostApportionmentRegistry::class)->has($strategyKey)
                ? app(CostApportionmentRegistry::class)->get($strategyKey)->name()
                : $strategyKey)
            : null;

        $profitAndLoss = $canViewCosts
            ? app(VirtualStockIntakeProfitAndLossBuilder::class)->build(
                $this->virtualStockIntake,
                $strategyKey,
                $strategyName,
            )
            : null;

        return [
            'statuses' => $registry->statuses(),
            'canConfirm' => Gate::check('confirm', $this->virtualStockIntake),
            'canReceive' => Gate::check('receive', $this->virtualStockIntake),
            'canReturn' => Gate::check('return', $this->virtualStockIntake),
            'canCancel' => Gate::check('cancel', $this->virtualStockIntake),
            'canUpdate' => Gate::check('update', $this->virtualStockIntake),
            'canDelete' => Gate::check('delete', $this->virtualStockIntake),
            'canViewCosts' => $canViewCosts,
            'apportionmentStrategy' => $strategyName,
            'profitAndLoss' => $profitAndLoss,
            'outstandingAllocations' => app(VirtualStockIntakeReturnGuard::class)
                ->outstandingAllocations($this->virtualStockIntake),
            'isLateReturn' => $this->virtualStockIntake->isLateReturn(),
            'allocationsCount' => $this->virtualStockIntake->allocationsCount(),
            'intakeTotalMinor' => (int) $this->virtualStockIntake->unit_cost * (int) $this->virtualStockIntake->quantity,
        ];
    }

    private function resolveApportionmentStrategy(): string
    {
        if (is_string($this->virtualStockIntake->cost_apportionment_strategy)
            && $this->virtualStockIntake->cost_apportionment_strategy !== '') {
            return $this->virtualStockIntake->cost_apportionment_strategy;
        }

        $resolution = ShortageResolution::query()
            ->where('virtual_stock_intake_id', $this->virtualStockIntake->id)
            ->first();

        $metadataStrategy = $resolution?->metadata['apportionment_strategy'] ?? null;

        if (is_string($metadataStrategy) && $metadataStrategy !== '') {
            return $metadataStrategy;
        }

        return (string) settings(
            'shortages.cost_apportionment_strategy',
            config('shortages.cost_apportionment_strategy', 'primary_job'),
        );
    }
}; ?>

<section class="w-full">
    @php
        $formatter = app(\App\Support\Formatter::class);
        $statusMeta = $statuses[$virtualStockIntake->status] ?? [];
    @endphp

    <x-signals.page-header :title="$virtualStockIntake->number">
        <x-slot:breadcrumbs>
            <a href="{{ route('virtual-stock.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Virtual Stock</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>{{ $virtualStockIntake->number }}</span>
        </x-slot:breadcrumbs>
        <x-slot:meta>
            <span class="s-badge {{ $statusMeta['badge_class'] ?? 's-badge-zinc' }}">{{ $statusMeta['label'] ?? $virtualStockIntake->status }}</span>
            @if($isLateReturn)
                <span class="s-badge s-badge-amber">Late return</span>
            @endif
        </x-slot:meta>
        <x-slot:actions>
            @if($canConfirm)
                <button type="button" wire:click="confirm" class="s-btn s-btn-sm s-btn-accent">Confirm</button>
            @endif
            @if($canReceive)
                <button type="button" wire:click="receive" class="s-btn s-btn-sm s-btn-accent">Receive</button>
            @endif
            @if($canReturn)
                <button type="button" wire:click="openReturnToSupplier" class="s-btn s-btn-sm s-btn-secondary">Return to supplier</button>
            @endif
            @if($canCancel)
                <button type="button" wire:click="cancel" wire:confirm="Cancel this virtual stock intake?" class="s-btn s-btn-sm s-btn-secondary">Cancel</button>
            @endif
            @if($canDelete)
                <button type="button" wire:click="deleteDraft" wire:confirm="Delete this draft intake?" class="s-btn s-btn-sm s-btn-danger">Delete</button>
            @endif
        </x-slot:actions>
    </x-signals.page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3 space-y-6">
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <x-signals.panel title="Product"><p>{{ $virtualStockIntake->product?->name ?? '—' }}</p></x-signals.panel>
            <x-signals.panel title="Store"><p>{{ $virtualStockIntake->store?->name ?? '—' }}</p></x-signals.panel>
            <x-signals.panel title="Quantity"><p style="font-family: var(--font-mono);">{{ $virtualStockIntake->quantity }}</p></x-signals.panel>
            <x-signals.panel title="Allocations"><p style="font-family: var(--font-mono);">{{ $allocationsCount }}</p></x-signals.panel>
        </div>
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <x-signals.panel title="Unit cost"><p style="font-family: var(--font-mono);">{{ $formatter->money($virtualStockIntake->unit_cost, $virtualStockIntake->currency_code) }}</p></x-signals.panel>
            @if($virtualStockIntake->returned_at)
                <x-signals.panel title="Returned">
                    <p>@localdatetime($virtualStockIntake->returned_at)</p>
                    @if($isLateReturn)
                        <span class="s-badge s-badge-amber mt-1">Late return</span>
                    @endif
                </x-signals.panel>
            @endif
        </div>
        <x-signals.panel title="Availability window">
            <p>@localdatetime($virtualStockIntake->available_from) → @localdatetime($virtualStockIntake->available_until)</p>
        </x-signals.panel>

        @if($canViewCosts && $profitAndLoss !== null)
            <x-signals.panel title="Cost apportionment &amp; P&amp;L">
                <div class="space-y-3">
                    <div class="flex flex-wrap gap-4 text-sm">
                        <div>
                            <span class="text-[var(--text-muted)]">Strategy</span>
                            <p>{{ $profitAndLoss['strategy_label'] ?? '—' }}</p>
                        </div>
                        <div>
                            <span class="text-[var(--text-muted)]">Intake total</span>
                            <p style="font-family: var(--font-mono);">{{ $formatter->money($profitAndLoss['intake_total_minor'], $virtualStockIntake->currency_code) }}</p>
                        </div>
                    </div>
                    @if($profitAndLoss['rows'] !== [])
                        <x-signals.table-wrap>
                            <table class="s-table">
                                <thead>
                                    <tr>
                                        <th>Opportunity</th>
                                        <th class="text-right">Charge-out</th>
                                        <th class="text-right">Apportioned cost</th>
                                        <th class="text-right">Margin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($profitAndLoss['rows'] as $row)
                                        <tr wire:key="pl-row-{{ $row['opportunity_id'] }}">
                                            <td>
                                                <a href="{{ route('opportunities.costs', $row['opportunity_id']) }}" wire:navigate class="text-[var(--link)] hover:underline">
                                                    {{ $row['opportunity_subject'] }}
                                                </a>
                                            </td>
                                            <td class="text-right" style="font-family: var(--font-mono);">{{ $formatter->money($row['revenue_minor'], $virtualStockIntake->currency_code) }}</td>
                                            <td class="text-right" style="font-family: var(--font-mono);">{{ $formatter->money($row['apportioned_cost_minor'], $virtualStockIntake->currency_code) }}</td>
                                            <td class="text-right" style="font-family: var(--font-mono);">{{ $formatter->money($row['margin_minor'], $virtualStockIntake->currency_code) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </x-signals.table-wrap>
                    @else
                        <p class="text-sm text-[var(--text-muted)]">No apportioned opportunity costs yet.</p>
                    @endif
                </div>
            </x-signals.panel>
        @endif
    </div>

    @if($showReturnConfirmModal)
        <flux:modal wire:model="showReturnConfirmModal" class="max-w-lg">
            <flux:heading size="lg">Return to supplier with outstanding allocations?</flux:heading>
            <p class="mt-2 text-sm text-[var(--text-muted)]">
                The following opportunities still have quantity out on hire. Returning this intake will void its supply contribution and remove apportioned costs without changing opportunity allocations.
            </p>
            @if($outstandingAllocations !== [])
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach($outstandingAllocations as $allocation)
                        <li wire:key="outstanding-{{ $allocation['opportunity_item_id'] }}">
                            <span class="font-medium">{{ $allocation['opportunity_subject'] }}</span>
                            — {{ $allocation['line_name'] }}
                            <span style="font-family: var(--font-mono);">({{ $allocation['quantity_out'] }} out)</span>
                        </li>
                    @endforeach
                </ul>
            @endif
            <div class="mt-4 flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showReturnConfirmModal', false)">Cancel</flux:button>
                <flux:button variant="danger" wire:click="confirmReturnToSupplier">Return anyway</flux:button>
            </div>
        </flux:modal>
    @endif
</section>
