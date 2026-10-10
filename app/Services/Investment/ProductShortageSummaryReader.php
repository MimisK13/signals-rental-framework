<?php

namespace App\Services\Investment;

use App\Data\ProductShortageSummary;
use App\Enums\InvestmentIntelligenceCompleteness;
use App\Models\AvailabilityDailySummary;
use App\Models\Product;
use App\Models\Store;
use Carbon\CarbonImmutable;

final class ProductShortageSummaryReader
{
    public function read(Product $product, CarbonImmutable $from, CarbonImmutable $until, CarbonImmutable $asOf): ProductShortageSummary
    {
        if (! $from->isStartOfDay() || ! $until->isStartOfDay() || $until->greaterThan($asOf)) {
            return new ProductShortageSummary($from, $until, InvestmentIntelligenceCompleteness::Partial, null, null, ['shortage_history_does_not_cover_the_requested_window']);
        }

        $storeIds = Store::query()->inDefaultQueries()->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $dayCount = $from->diffInDays($until);

        if ($storeIds === [] || $dayCount <= 0) {
            return new ProductShortageSummary($from, $until, InvestmentIntelligenceCompleteness::Insufficient, null, null, ['shortage_history_unavailable']);
        }

        $rows = AvailabilityDailySummary::query()
            ->where('product_id', $product->id)
            ->whereIn('store_id', $storeIds)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<', $until->toDateString())
            ->where('calculated_at', '<=', $asOf)
            ->get();

        $coveredDays = $rows->pluck('date')->map(fn ($date): string => CarbonImmutable::parse($date)->toDateString())->unique()->count();

        if ($coveredDays < $dayCount) {
            return new ProductShortageSummary($from, $until, InvestmentIntelligenceCompleteness::Partial, null, null, ['shortage_history_does_not_cover_the_requested_window']);
        }

        $shortageDays = $rows->where('has_shortage', true)->groupBy(fn ($row): string => CarbonImmutable::parse($row->date)->toDateString())->count();
        $peak = $rows->where('has_shortage', true)->max(fn ($row): int => max(0, -((int) $row->min_available)));

        return new ProductShortageSummary($from, $until, InvestmentIntelligenceCompleteness::Complete, $shortageDays, $peak ?: 0);
    }
}
