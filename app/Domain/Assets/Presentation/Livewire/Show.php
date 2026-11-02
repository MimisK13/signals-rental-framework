<?php

declare(strict_types=1);

namespace App\Domain\Assets\Presentation\Livewire;

use App\Domain\Assets\Actions\ConfirmAssetLost;
use App\Domain\Assets\Actions\MarkAssetMissing;
use App\Domain\Assets\Actions\ReactivateAsset;
use App\Domain\Assets\Actions\RestoreAsset;
use App\Domain\Assets\Actions\RetireAsset;
use App\Domain\Assets\Enums\AssetLifecycleStatus;
use App\Domain\Assets\Enums\InspectionResult;
use App\Domain\Assets\InspectionActions\CancelAssetInspection;
use App\Domain\Assets\InspectionActions\CompleteAssetInspection;
use App\Domain\Assets\InspectionActions\InitializeAssetInspection;
use App\Domain\Assets\InspectionActions\StartAssetInspection;
use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Models\AssetInspection;
use App\Domain\Assets\Models\InspectionPolicy;
use App\Domain\Assets\Services\AssetFinancialsCalculator;
use App\Domain\Assets\Services\AssetFinancialsResult;
use App\Domain\Assets\Services\AssetInspectionComplianceReader;
use App\Domain\Stock\Models\StockLevel;
use App\Support\Formatter;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Show extends Component
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
        $stockLevel = StockLevel::query()->where('asset_id', $this->asset->id)->first();

        if ($stockLevel === null) {
            $this->restoreStoreId = '';
            $this->restoreLocation = '';
        } else {
            $this->restoreStoreId = (string) $stockLevel->store_id;
            $this->restoreLocation = (string) ($stockLevel->location ?? '');
        }
    }

    public function rendering(View $view): void
    {
        $view->title($this->asset->product()->value('name') ?? 'Asset');
    }

    /** @return array<string, mixed> */
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

    public function render(): ViewContract
    {
        return view('assets::show', $this->with());
    }
}
