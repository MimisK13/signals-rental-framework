<?php

use App\Models\Asset;
use App\Actions\Assets\ConfirmAssetLost;
use App\Actions\Assets\MarkAssetMissing;
use App\Actions\Assets\ReactivateAsset;
use App\Actions\Assets\RestoreAsset;
use App\Actions\Assets\RetireAsset;
use App\Enums\AssetLifecycleStatus;
use App\Actions\Inspections\CancelAssetInspection;
use App\Actions\Inspections\CompleteAssetInspection;
use App\Actions\Inspections\InitializeAssetInspection;
use App\Actions\Inspections\StartAssetInspection;
use App\Enums\InspectionResult;
use App\Models\AssetInspection;
use App\Models\InspectionPolicy;
use App\Services\Assets\AssetFinancialsCalculator;
use App\Services\Assets\AssetInspectionComplianceReader;
use App\Services\Assets\AssetFinancialsResult;
use App\Support\Formatter;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    public Asset $asset;

    protected ?AssetFinancialsResult $financialResult = null;

    public string $firstDueDate = '';

    public string $scheduledStartAt = '';

    public string $scheduledEndAt = '';

    public string $lifecycleReason = '';

    public string $restoreStoreId = '';

    public string $restoreLocation = '';

    public string $dispositionProceeds = '';

    public string $dispositionCurrencyCode = '';

    public function mount(Asset $asset): void
    {
        Gate::authorize('stock.view');

        $this->asset = $asset->load([
            'product',
            'owner',
            'supplier',
            'purchaseOrderItem.purchaseOrder',
            'currentStockLevel.store',
            'currentStockLevel.member',
            'usages.opportunityItem.opportunity',
            'repairs.product',
            'repairs.store',
            'inspections.inspectionPolicy',
            'inspections.inspectorUser',
            'inspections.inspectorMember',
            'inspections.attachments',
            'lifecycleEvents.actorUser',
            'lifecycleEvents.store',
        ]);

        $this->financialResult = app(AssetFinancialsCalculator::class)->calculate($this->asset);
        $this->firstDueDate = now()->addYear()->toDateString();
        $this->restoreStoreId = (string) ($this->asset->currentStockLevel?->store_id ?? '');
        $this->restoreLocation = (string) ($this->asset->currentStockLevel?->location ?? '');
    }

    public function rendering(View $view): void
    {
        $view->title($this->asset->product?->name ?? 'Asset');
    }

    public function with(): array
    {
        $policies = $this->asset->lifecycleStatus() === AssetLifecycleStatus::Retired
            ? collect()
            : ($this->asset->product?->inspectionPolicies()->where('is_active', true)->get() ?? collect());
        $reader = app(AssetInspectionComplianceReader::class);

        return [
            'stockLevel' => $this->asset->currentStockLevel,
            'financials' => $this->financialResult,
            'formatter' => app(Formatter::class),
            'inspectionPolicies' => $policies,
            'inspectionCompliance' => $policies->mapWithKeys(fn (InspectionPolicy $policy): array => [
                $policy->id => $reader->forAssetPolicy($this->asset, $policy),
            ]),
            'lifecycleEvents' => $this->asset->lifecycleEvents->sortByDesc('occurred_at'),
        ];
    }

    public function markMissing(): void
    {
        (new MarkAssetMissing)($this->asset, ['reason' => $this->lifecycleReason ?: null]);
        $this->refreshAsset();
    }

    public function confirmLost(): void
    {
        (new ConfirmAssetLost)($this->asset, ['reason' => $this->lifecycleReason ?: null]);
        $this->refreshAsset();
    }

    public function restore(): void
    {
        $this->validate([
            'restoreStoreId' => ['nullable', 'integer', 'exists:stores,id'],
            'restoreLocation' => ['nullable', 'string', 'max:255'],
        ]);

        (new RestoreAsset)($this->asset, [
            'store_id' => $this->restoreStoreId !== '' ? (int) $this->restoreStoreId : null,
            'location' => $this->restoreLocation !== '' ? $this->restoreLocation : null,
            'reason' => $this->lifecycleReason ?: null,
        ]);
        $this->refreshAsset();
    }

    public function retire(string $disposition = 'retired'): void
    {
        (new RetireAsset)($this->asset, [
            'disposition' => $disposition,
            'reason' => $this->lifecycleReason ?: null,
            'proceeds_amount' => $this->dispositionProceeds !== '' ? (int) $this->dispositionProceeds : null,
            'proceeds_currency_code' => $this->dispositionCurrencyCode !== '' ? strtoupper($this->dispositionCurrencyCode) : null,
        ]);
        $this->refreshAsset();
    }

    public function reactivate(): void
    {
        (new ReactivateAsset)($this->asset, [
            'store_id' => $this->restoreStoreId !== '' ? (int) $this->restoreStoreId : null,
            'location' => $this->restoreLocation !== '' ? $this->restoreLocation : null,
            'reason' => $this->lifecycleReason ?: null,
        ]);
        $this->refreshAsset();
    }

    private function refreshAsset(): void
    {
        $this->asset = $this->asset->fresh([
            'product',
            'currentStockLevel.store',
            'repairs.product',
            'repairs.store',
            'inspections.inspectionPolicy',
            'inspections.inspectorUser',
            'inspections.inspectorMember',
            'inspections.attachments',
            'lifecycleEvents.actorUser',
            'lifecycleEvents.store',
        ]) ?? $this->asset;
    }

    public function initializeInspection(int $policyId): void
    {
        $policy = InspectionPolicy::query()->findOrFail($policyId);
        $this->validate([
            'firstDueDate' => ['required', 'date'],
            'scheduledStartAt' => ['nullable', 'date'],
            'scheduledEndAt' => ['nullable', 'date', 'after:scheduledStartAt'],
        ]);
        (new InitializeAssetInspection)(
            $this->asset,
            $policy,
            $this->firstDueDate,
            $this->scheduledStartAt !== '' ? $this->scheduledStartAt : null,
            $this->scheduledEndAt !== '' ? $this->scheduledEndAt : null,
        );
        $this->asset->refresh()->load('inspections.inspectionPolicy');
    }

    public function startInspection(int $inspectionId): void
    {
        (new StartAssetInspection)(AssetInspection::query()->findOrFail($inspectionId));
        $this->asset->refresh()->load('inspections.inspectionPolicy');
    }

    public function completeInspection(int $inspectionId, string $result): void
    {
        (new CompleteAssetInspection)(AssetInspection::query()->findOrFail($inspectionId), InspectionResult::from($result));
        $this->asset->refresh()->load('inspections.inspectionPolicy');
    }

    public function cancelInspection(int $inspectionId): void
    {
        (new CancelAssetInspection)(AssetInspection::query()->findOrFail($inspectionId));
        $this->asset->refresh()->load('inspections.inspectionPolicy');
    }

    public function openInspectionUpload(int $inspectionId): void
    {
        $this->dispatch('open-file-upload', modelId: $inspectionId);
    }

    public function formatPercentage(?string $ratio): string
    {
        if ($ratio === null) {
            return '—';
        }

        return BigDecimal::of($ratio)
            ->multipliedBy('100')
            ->toScale(2, RoundingMode::HALF_UP)
            ->__toString().'%';
    }

    public function progressWidth(?string $ratio): string
    {
        if ($ratio === null) {
            return '0';
        }

        $percentage = BigDecimal::of($ratio)->multipliedBy('100');

        return $percentage->isGreaterThan('100')
            ? '100'
            : $percentage->toScale(2, RoundingMode::HALF_UP)->__toString();
    }

    public function hasReachedBreakEven(?string $ratio): bool
    {
        return $ratio !== null && BigDecimal::of($ratio)->isGreaterThanOrEqualTo('1');
    }

    public function unavailableReason(string $reason): string
    {
        return match ($reason) {
            'acquisition_unknown' => 'Acquisition cost is not available.',
            'acquisition_invalid' => 'Acquisition cost is not valid for this calculation.',
            'revenue_ambiguous' => 'Some rental revenue cannot be reliably attributed to this physical asset.',
            'revenue_currency_unavailable' => 'Rental revenue currency cannot be reconciled with the asset base currency.',
            'expense_incomplete' => 'One or more operating expenses lack normalized financial data.',
            'expense_currency_unavailable' => 'One or more operating expenses use an incompatible base currency.',
            'usage_incomplete' => 'One or more usage records have an invalid duration.',
            default => 'This metric is not available from the current data.',
        };
    }
}; ?>

<section class="w-full" x-data="{ tab: 'overview' }">
    <x-signals.page-header :title="$asset->product?->name ?? 'Asset'">
        <x-slot:breadcrumbs>
            @if($asset->product)
                <a href="{{ route('products.show', $asset->product) }}" wire:navigate.hover class="text-[var(--link)] hover:underline">{{ $asset->product->name }}</a>
                <span class="mx-1 text-[var(--text-muted)]">/</span>
            @endif
            <span>Asset</span>
        </x-slot:breadcrumbs>
        <x-slot:meta>
            <div class="flex flex-wrap items-center gap-2">
                @if($stockLevel?->asset_number)
                    <span class="text-sm font-semibold text-[var(--text-primary)]" style="font-family: var(--font-display);">{{ $stockLevel->asset_number }}</span>
                @endif
                @if($stockLevel?->serial_number)
                    <span class="text-sm text-[var(--text-muted)]">SN {{ $stockLevel->serial_number }}</span>
                @endif
            </div>
        </x-slot:meta>
        <x-slot:actions>
            @if($stockLevel)
                <a href="{{ route('stock-levels.show', $stockLevel) }}" wire:navigate.hover class="s-btn s-btn-sm s-btn-ghost">View Stock Level</a>
            @endif
        </x-slot:actions>
    </x-signals.page-header>

    <nav class="app-subnav">
        <div class="flex h-full items-center gap-0 overflow-x-auto">
            <button type="button" x-on:click="tab = 'overview'" class="subnav-link" x-bind:class="{ 'active': tab === 'overview' }">Overview</button>
            <button type="button" x-on:click="tab = 'history'" class="subnav-link" x-bind:class="{ 'active': tab === 'history' }">History</button>
            <button type="button" x-on:click="tab = 'maintenance'" class="subnav-link" x-bind:class="{ 'active': tab === 'maintenance' }">Maintenance</button>
            <button type="button" x-on:click="tab = 'inspections'" class="subnav-link" x-bind:class="{ 'active': tab === 'inspections' }">Inspections</button>
            <a href="{{ route('assets.utilization', $asset) }}" wire:navigate.hover class="subnav-link">Utilization</a>
            <a href="{{ route('assets.investment', $asset) }}" wire:navigate.hover class="subnav-link">Investment</a>
            <button type="button" x-on:click="tab = 'financials'" class="subnav-link" x-bind:class="{ 'active': tab === 'financials' }">Financials</button>
        </div>
    </nav>

    <div x-show="tab === 'overview'" class="grid grid-cols-[1fr_320px] gap-6 px-6 py-4 max-lg:grid-cols-1 max-md:px-5 max-sm:px-3">
        <div class="space-y-6">
            <x-signals.panel title="Physical Identity">
                <x-signals.data-list layout="grid" :items="array_filter([
                    ['label' => 'Product', 'value' => $asset->product?->name ?? '—', 'href' => $asset->product ? route('products.show', $asset->product) : null],
                    $stockLevel?->asset_number ? ['label' => 'Asset Number', 'value' => $stockLevel->asset_number, 'mono' => true] : null,
                    $stockLevel?->serial_number ? ['label' => 'Serial Number', 'value' => $stockLevel->serial_number, 'mono' => true] : null,
                    $stockLevel?->barcode ? ['label' => 'Barcode', 'value' => $stockLevel->barcode, 'mono' => true] : null,
                    $stockLevel?->store ? ['label' => 'Store', 'value' => $stockLevel->store->name] : null,
                    $stockLevel?->location ? ['label' => 'Location', 'value' => $stockLevel->location] : null,
                    ['label' => 'Lifecycle', 'value' => $asset->lifecycleStatus()->label()],
                    ['label' => 'Ownership', 'value' => $asset->ownership_type?->label() ?? 'Unknown'],
                    $asset->owner ? ['label' => 'Owner', 'value' => $asset->owner->name] : null,
                ])" />
            </x-signals.panel>
        </div>

        <div class="space-y-6">
            <x-signals.panel title="Acquisition">
                <x-signals.data-list layout="vertical" :items="array_filter([
                    $asset->acquired_at ? ['label' => 'Acquired', 'value' => $formatter->date($asset->acquired_at)] : null,
                    $asset->base_acquisition_amount !== null ? ['label' => 'Base Cost', 'value' => $formatter->money($asset->base_acquisition_amount, $asset->base_currency_code)] : null,
                    $asset->acquisition_amount !== null ? ['label' => 'Original Cost', 'value' => $formatter->money($asset->acquisition_amount, $asset->acquisition_currency_code)] : null,
                    $asset->supplier ? ['label' => 'Supplier', 'value' => $asset->supplier->name] : null,
                    $asset->estimated_resale_value !== null ? ['label' => 'Estimated Resale', 'value' => $formatter->money($asset->estimated_resale_value, $asset->estimated_resale_currency_code)] : null,
                ])">
                    @if($asset->acquired_at === null && $asset->base_acquisition_amount === null && $asset->acquisition_amount === null && $asset->supplier === null && $asset->estimated_resale_value === null)
                        <span class="text-sm text-[var(--text-muted)]">No acquisition information recorded.</span>
                    @endif
                </x-signals.data-list>
            </x-signals.panel>
        </div>
    </div>

    <div x-show="tab === 'inspections'" x-cloak class="space-y-6 px-6 py-4 max-md:px-5 max-sm:px-3">
        <x-signals.panel title="Inspection Compliance">
            @if($inspectionPolicies->isEmpty())
                <x-signals.empty title="No inspection policies" description="This Product has no active inspection policies." />
            @else
                <div class="mb-4 flex flex-wrap items-end gap-3">
                    <flux:input wire:model="firstDueDate" type="date" label="First due date" />
                    <flux:input wire:model="scheduledStartAt" type="datetime-local" label="Appointment start (optional)" />
                    <flux:input wire:model="scheduledEndAt" type="datetime-local" label="Appointment end (optional)" />
                </div>
                <div class="s-table-wrap">
                    <table class="s-table w-full">
                        <thead><tr><th>Policy</th><th>State</th><th>Next due</th><th>Last completed</th><th></th></tr></thead>
                        <tbody>
                            @foreach($inspectionPolicies as $policy)
                                @php($compliance = $inspectionCompliance[$policy->id])
                                <tr wire:key="asset-inspection-policy-{{ $policy->id }}">
                                    <td>{{ $policy->name }}</td>
                                    <td>{{ $compliance->state->label() }}</td>
                                    <td>{{ $compliance->nextDueAt?->toDateString() ?? '—' }}</td>
                                    <td>{{ $compliance->lastCompletedInspection?->completed_at?->toDateString() ?? '—' }}</td>
                                    <td>
                                        @if($compliance->latestInspection === null)
                                            <flux:button wire:click="initializeInspection({{ $policy->id }})" size="sm" variant="primary">Initialize</flux:button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-signals.panel>

        <x-signals.panel title="Inspection History">
            @if($asset->inspections->isEmpty())
                <x-signals.empty title="No inspection history" description="This Asset has not been enrolled in an inspection policy." />
            @else
                <div class="s-table-wrap">
                    <table class="s-table w-full">
                        <thead><tr><th>Policy</th><th>Due</th><th>Status</th><th>Result</th><th>Completed</th><th></th></tr></thead>
                        <tbody>
                            @foreach($asset->inspections->sortByDesc('due_at') as $inspection)
                                <tr wire:key="asset-inspection-{{ $inspection->id }}">
                                    <td>{{ $inspection->inspectionPolicy?->name ?? '—' }}</td>
                                    <td>{{ $inspection->due_at->toDateString() }}</td>
                                    <td>{{ $inspection->status->label() }}</td>
                                    <td>{{ $inspection->result?->label() ?? '—' }}</td>
                                    <td>{{ $inspection->completed_at?->toDateString() ?? '—' }}</td>
                                    <td class="flex flex-wrap gap-2">
                                        @if($inspection->status === \App\Enums\InspectionStatus::Pending)
                                            <flux:button wire:click="startInspection({{ $inspection->id }})" size="sm">Start</flux:button>
                                        @elseif($inspection->status === \App\Enums\InspectionStatus::InProgress)
                                            <flux:button wire:click="completeInspection({{ $inspection->id }}, 'passed')" size="sm" variant="primary">Pass</flux:button>
                                            <flux:button wire:click="completeInspection({{ $inspection->id }}, 'failed')" size="sm">Fail</flux:button>
                                        @endif
                                        @if(in_array($inspection->status, [\App\Enums\InspectionStatus::Pending, \App\Enums\InspectionStatus::InProgress], true))
                                            <flux:button wire:click="cancelInspection({{ $inspection->id }})" size="sm">Cancel</flux:button>
                                        @endif
                                        <flux:button wire:click="openInspectionUpload({{ $inspection->id }})" size="sm">Attach</flux:button>
                                    </td>
                                </tr>
                                <livewire:components.file-upload-modal :model-type="\App\Models\AssetInspection::class" :model-id="$inspection->id" :key="'inspection-upload-'.$inspection->id" />
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-signals.panel>
    </div>

    <div x-show="tab === 'history'" x-cloak class="px-6 py-4 max-md:px-5 max-sm:px-3">
        <x-signals.panel title="Lifecycle History" class="mb-6">
            <div class="mb-4 flex flex-wrap items-end gap-3">
                <flux:input wire:model="lifecycleReason" label="Reason or notes" placeholder="Optional" />
                <flux:input wire:model="dispositionProceeds" type="number" label="Proceeds (minor units)" placeholder="Optional" />
                <flux:input wire:model="dispositionCurrencyCode" label="Currency" placeholder="EUR" />
                @can('stock.adjust')
                    @if($asset->lifecycleStatus() === AssetLifecycleStatus::Active)
                        <flux:button wire:click="markMissing" size="sm">Mark Missing</flux:button>
                        <flux:button wire:click="retire" size="sm">Retire</flux:button>
                        <flux:button wire:click="retire('sold')" size="sm">Record Sold</flux:button>
                        <flux:button wire:click="retire('disposed')" size="sm">Record Disposed</flux:button>
                    @elseif($asset->lifecycleStatus() === AssetLifecycleStatus::Missing)
                        <flux:button wire:click="restore" size="sm" variant="primary">Mark Found</flux:button>
                        <flux:button wire:click="confirmLost" size="sm">Confirm Lost</flux:button>
                    @elseif($asset->lifecycleStatus() === AssetLifecycleStatus::Lost)
                        <flux:button wire:click="restore" size="sm" variant="primary">Mark Found</flux:button>
                        <flux:button wire:click="retire" size="sm">Retire</flux:button>
                        <flux:button wire:click="retire('sold')" size="sm">Record Sold</flux:button>
                        <flux:button wire:click="retire('disposed')" size="sm">Record Disposed</flux:button>
                    @elseif($asset->lifecycleStatus() === AssetLifecycleStatus::Retired && !$lifecycleEvents->contains(fn ($event): bool => $event->hasTerminalDisposition()))
                        <flux:button wire:click="reactivate" size="sm" variant="primary">Reactivate</flux:button>
                    @endif
                @endcan
            </div>
            @if($asset->lifecycleStatus() !== AssetLifecycleStatus::Active)
                <div class="mb-4 flex flex-wrap items-end gap-3">
                    <flux:input wire:model="restoreStoreId" type="number" label="Restore Store ID (if needed)" />
                    <flux:input wire:model="restoreLocation" label="Restore location (if needed)" />
                </div>
            @endif
            @if($lifecycleEvents->isEmpty())
                <x-signals.empty title="No lifecycle history" description="Lifecycle history begins when a transition is recorded." />
            @else
                <div class="s-table-wrap">
                    <table class="s-table w-full">
                        <thead><tr><th>Date</th><th>Event</th><th>Transition</th><th>Actor</th><th>Reason</th></tr></thead>
                        <tbody>
                            @foreach($lifecycleEvents as $event)
                                <tr wire:key="asset-lifecycle-event-{{ $event->id }}">
                                    <td>{{ $formatter->dateTime($event->occurred_at) }}</td>
                                    <td>{{ $event->event_type->label() }}{{ $event->disposition ? ' · '.$event->disposition->label() : '' }}</td>
                                    <td>{{ $event->previous_status?->label() ?? '—' }} → {{ $event->new_status->label() }}</td>
                                    <td>{{ $event->actorUser?->name ?? $event->actorMember?->name ?? '—' }}</td>
                                    <td>{{ $event->reason ?? $event->notes ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-signals.panel>

        <x-signals.panel title="Actual Usage History">
            @if($asset->usages->isEmpty())
                <x-signals.empty title="No usage history" description="This asset has not been recorded as physically dispatched yet." />
            @else
                <div class="s-table-wrap">
                    <table class="s-table w-full">
                        <thead>
                            <tr>
                                <th>Opportunity</th>
                                <th>Dispatched</th>
                                <th>Returned</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($asset->usages->sortByDesc('dispatched_at') as $usage)
                                <tr wire:key="asset-usage-{{ $usage->id }}">
                                    <td>
                                        @if($usage->opportunityItem?->opportunity)
                                            <a href="{{ route('opportunities.show', $usage->opportunityItem->opportunity) }}" wire:navigate.hover class="font-medium text-[var(--link)] hover:underline">
                                                {{ $usage->opportunityItem->opportunity->subject }}
                                            </a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $formatter->dateTime($usage->dispatched_at) }}</td>
                                    <td>{{ $usage->returned_at ? $formatter->dateTime($usage->returned_at) : 'Active' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-signals.panel>
    </div>

    <div x-show="tab === 'maintenance'" x-cloak class="px-6 py-4 max-md:px-5 max-sm:px-3">
        <x-signals.panel title="Repairs">
            <div class="mb-4 flex justify-end">
                @can('create', \App\Models\Repair::class)
                    <a href="{{ route('repairs.create', ['asset_id' => $asset->id]) }}" wire:navigate.hover class="s-btn s-btn-sm s-btn-primary">Report repair</a>
                @endcan
            </div>
            @if($asset->repairs->isEmpty())
                <x-signals.empty title="No repairs" description="This asset has no repair history." />
            @else
                <div class="s-table-wrap">
                    <table class="s-table w-full">
                        <thead><tr><th>Status</th><th>Reported</th><th>Period</th><th>Usable</th><th></th></tr></thead>
                        <tbody>
                            @foreach($asset->repairs->sortByDesc('reported_at') as $repair)
                                <tr wire:key="asset-repair-{{ $repair->id }}">
                                    <td>{{ $repair->status->label() }}</td>
                                    <td>{{ $formatter->dateTime($repair->reported_at) }}</td>
                                    <td>{{ $formatter->dateTime($repair->started_at ?? $repair->reported_at) }} → {{ $repair->completed_at ? $formatter->dateTime($repair->completed_at) : 'Open' }}</td>
                                    <td>{{ $repair->is_usable ? 'Yes' : 'No' }}</td>
                                    <td><a href="{{ route('repairs.show', $repair) }}" wire:navigate.hover class="text-[var(--link)] hover:underline">View</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-signals.panel>
    </div>

    <div x-show="tab === 'financials'" x-cloak class="space-y-6 px-6 py-4 max-md:px-5 max-sm:px-3">
        @if(!$financials->revenueComplete)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                <div class="font-semibold">Financial history incomplete</div>
                <div class="mt-1">Confirmed revenue is shown below, but complete Asset financial performance cannot be calculated reliably.</div>
            </div>
        @endif

        <x-signals.panel title="Cost Recovery">
            @if($financials->costRecoveryRatio !== null)
                <div class="space-y-4">
                    <div class="text-3xl font-bold text-[var(--text-primary)]" style="font-family: var(--font-display);">
                        {{ $this->formatPercentage($financials->costRecoveryRatio) }}
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-[var(--card-border)]">
                        <div class="h-full rounded-full bg-[var(--green)]" style="width: {{ $this->progressWidth($financials->costRecoveryRatio) }}%;"></div>
                    </div>
                    @if($this->hasReachedBreakEven($financials->costRecoveryRatio))
                        <div class="space-y-1 text-sm">
                            <div class="font-medium text-[var(--text-primary)]">Acquisition cost fully recovered</div>
                            <div class="text-[var(--text-secondary)]">
                                {{ $formatter->money($financials->investmentProfit, $financials->currencyCode) }} above break-even
                            </div>
                        </div>
                    @else
                        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                            <span class="text-[var(--text-secondary)]">
                                {{ $formatter->money($financials->netRentalIncome, $financials->currencyCode) }} of {{ $formatter->money($asset->base_acquisition_amount, $financials->currencyCode) }} recovered
                            </span>
                            <span class="font-medium text-[var(--text-primary)]">
                                {{ $formatter->money($financials->remainingToBreakEven, $financials->currencyCode) }} remaining to break-even
                            </span>
                        </div>
                    @endif
                </div>
            @else
                <x-signals.data-list layout="vertical" :items="[
                    ['label' => 'Cost Recovery', 'value' => '—'],
                    ['label' => 'Remaining to Break-even', 'value' => '—'],
                ]" />
            @endif
        </x-signals.panel>

        <div class="grid grid-cols-2 gap-6 max-lg:grid-cols-1">
            <x-signals.panel title="Performance">
                <x-signals.data-list layout="vertical" :items="[
                    ['label' => 'Rental Revenue', 'value' => $financials->revenueComplete || $financials->confirmedAttributedRentalRevenue > 0 ? $formatter->money($financials->confirmedAttributedRentalRevenue, $financials->currencyCode) : '—'],
                    ['label' => 'Operating Expenses', 'value' => $financials->operatingExpenses !== null ? $formatter->money($financials->operatingExpenses, $financials->currencyCode) : '—'],
                    ['label' => 'Net Rental Income', 'value' => $financials->netRentalIncome !== null ? $formatter->money($financials->netRentalIncome, $financials->currencyCode) : '—'],
                ]" />
            </x-signals.panel>

            <x-signals.panel title="Investment">
                <x-signals.data-list layout="vertical" :items="[
                    ['label' => 'Acquisition Cost', 'value' => $asset->base_acquisition_amount !== null ? $formatter->money($asset->base_acquisition_amount, $financials->currencyCode) : '—'],
                    ['label' => 'Investment Profit', 'value' => $financials->investmentProfit !== null ? $formatter->money($financials->investmentProfit, $financials->currencyCode) : '—'],
                    ['label' => 'Investment ROI', 'value' => $this->formatPercentage($financials->investmentRoi)],
                ]" />
            </x-signals.panel>
        </div>

        <x-signals.panel title="Usage">
            <div class="grid grid-cols-3 gap-4 max-sm:grid-cols-1">
                <div class="rounded-lg border border-[var(--card-border)] px-4 py-3">
                    <div class="text-2xl font-bold text-[var(--text-primary)]" style="font-family: var(--font-display);">{{ $financials->rentalCount }}</div>
                    <div class="mt-1 text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">Rentals</div>
                </div>
                <div class="rounded-lg border border-[var(--card-border)] px-4 py-3">
                    <div class="text-2xl font-bold text-[var(--text-primary)]" style="font-family: var(--font-display);">{{ $financials->completedRentalCount }}</div>
                    <div class="mt-1 text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">Completed Rentals</div>
                </div>
                <div class="rounded-lg border border-[var(--card-border)] px-4 py-3">
                    <div class="text-2xl font-bold text-[var(--text-primary)]" style="font-family: var(--font-display);">{{ $financials->actualRentalDays ?? '—' }}</div>
                    <div class="mt-1 text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">Rental Days</div>
                </div>
            </div>
        </x-signals.panel>

        @if($financials->unavailableReasons !== [])
            <x-signals.panel title="Why some metrics are unavailable">
                <ul class="space-y-2 text-sm text-[var(--text-secondary)]">
                    @foreach($financials->unavailableReasons as $reason)
                        <li class="flex gap-2"><span class="text-[var(--text-muted)]">•</span><span>{{ $this->unavailableReason($reason) }}</span></li>
                    @endforeach
                </ul>
            </x-signals.panel>
        @endif
    </div>
</section>
