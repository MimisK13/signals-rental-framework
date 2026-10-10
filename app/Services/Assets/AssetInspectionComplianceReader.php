<?php

namespace App\Services\Assets;

use App\Enums\InspectionComplianceState;
use App\Enums\InspectionResult;
use App\Enums\InspectionStatus;
use App\Enums\StockMethod;
use App\Models\Asset;
use App\Models\AssetInspection;
use App\Models\InspectionPolicy;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AssetInspectionComplianceReader
{
    public function forAssetPolicy(
        Asset $asset,
        InspectionPolicy $policy,
        ?CarbonImmutable $asOf = null,
        int $dueSoonDays = 30,
    ): AssetInspectionCompliance {
        $asOf ??= CarbonImmutable::now();

        $inspections = $asset->relationLoaded('inspections')
            ? $asset->inspections->where('inspection_policy_id', $policy->id)
            : $asset->inspections()->where('inspection_policy_id', $policy->id)->get();

        if (! $policy->is_active) {
            return new AssetInspectionCompliance(
                InspectionComplianceState::Unknown,
                $inspections->sortByDesc('due_at')->first(),
                $inspections->where('status', InspectionStatus::Completed)->sortByDesc('completed_at')->first(),
                null,
            );
        }

        return $this->resolve($inspections, $policy, $asOf, $dueSoonDays);
    }

    /** @return array<string, int> */
    public function summarizeProduct(Product $product, ?CarbonImmutable $asOf = null, int $dueSoonDays = 30): array
    {
        $asOf ??= CarbonImmutable::now();
        $policies = $product->inspectionPolicies()->where('is_active', true)->get();
        $assets = $product->assets()
            ->where('lifecycle_status', '!=', 'retired')
            ->whereHas('product', fn ($query) => $query->where('stock_method', StockMethod::Serialised))
            ->with(['inspections' => fn ($query) => $query->whereIn('inspection_policy_id', $policies->pluck('id'))])
            ->get();
        $counts = array_fill_keys(array_map(static fn (InspectionComplianceState $state): string => $state->value, InspectionComplianceState::cases()), 0);

        foreach ($assets as $asset) {
            $states = $policies->map(fn (InspectionPolicy $policy): InspectionComplianceState => $this->resolve(
                $asset->inspections->where('inspection_policy_id', $policy->id),
                $policy,
                $asOf,
                $dueSoonDays,
            )->state);

            $state = $this->aggregateStates($states);
            $counts[$state->value]++;
        }

        return $counts;
    }

    /** @param Collection<int, AssetInspection> $inspections */
    private function resolve(Collection $inspections, InspectionPolicy $policy, CarbonImmutable $asOf, int $dueSoonDays): AssetInspectionCompliance
    {
        $relevant = $inspections
            ->reject(fn (AssetInspection $inspection): bool => $inspection->statusEnum() === InspectionStatus::Cancelled)
            ->sortByDesc(fn (AssetInspection $inspection): int => $inspection->getAttribute('due_at') !== null ? CarbonImmutable::parse((string) $inspection->getAttribute('due_at'))->getTimestamp() : 0)
            ->values();
        $latest = $relevant->first();
        $completed = $relevant
            ->filter(fn (AssetInspection $inspection): bool => $inspection->statusEnum() === InspectionStatus::Completed)
            ->sortByDesc(fn (AssetInspection $inspection): int => $inspection->completed_at !== null ? CarbonImmutable::parse($inspection->completed_at)->getTimestamp() : 0)
            ->first();
        $nextDue = $this->nextDue($relevant, $completed, $policy);

        if ($completed?->resultEnum() === InspectionResult::Failed) {
            return new AssetInspectionCompliance(InspectionComplianceState::Failed, $latest, $completed, $nextDue);
        }

        if ($nextDue === null) {
            return new AssetInspectionCompliance(InspectionComplianceState::Unknown, $latest, $completed, null);
        }

        $today = $asOf->startOfDay();
        $dueSoonLimit = $today->addDays(max(0, $dueSoonDays));
        $state = $nextDue->lt($today)
            ? InspectionComplianceState::Overdue
            : ($nextDue->lte($dueSoonLimit) ? InspectionComplianceState::DueSoon : InspectionComplianceState::Valid);

        return new AssetInspectionCompliance($state, $latest, $completed, $nextDue);
    }

    /** @param Collection<int, AssetInspection> $inspections */
    private function nextDue(Collection $inspections, ?AssetInspection $completed, InspectionPolicy $policy): ?CarbonImmutable
    {
        $scheduled = $inspections
            ->filter(fn (AssetInspection $inspection): bool => in_array($inspection->statusEnum(), [InspectionStatus::Pending, InspectionStatus::InProgress], true))
            ->sortByDesc(fn (AssetInspection $inspection): int => $inspection->getAttribute('due_at') !== null ? CarbonImmutable::parse((string) $inspection->getAttribute('due_at'))->getTimestamp() : 0)
            ->first();

        if ($scheduled !== null) {
            return CarbonImmutable::parse($scheduled->due_at);
        }

        if ($completed === null) {
            return $inspections->first()?->due_at
                ? CarbonImmutable::parse($inspections->first()->due_at)
                : null;
        }

        return CarbonImmutable::parse($completed->due_at)->addMonthsNoOverflow($policy->interval_months);
    }

    /** @param Collection<int, InspectionComplianceState> $states */
    private function aggregateStates(Collection $states): InspectionComplianceState
    {
        foreach ([InspectionComplianceState::Failed, InspectionComplianceState::Overdue, InspectionComplianceState::DueSoon, InspectionComplianceState::Unknown, InspectionComplianceState::Valid] as $state) {
            if ($states->contains($state)) {
                return $state;
            }
        }

        return InspectionComplianceState::Unknown;
    }
}
