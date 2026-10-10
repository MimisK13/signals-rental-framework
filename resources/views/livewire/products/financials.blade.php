<?php

use App\Models\Product;
use App\Services\Assets\ProductFinancialsCalculator;
use App\Services\Assets\ProductFinancialsResult;
use App\Services\Products\ProductExternalRentalCostCalculator;
use App\Services\Products\ProductExternalRentalCostResult;
use App\Support\Formatter;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    public Product $product;

    protected ?ProductFinancialsResult $financialResult = null;

    protected ?ProductExternalRentalCostResult $externalCostResult = null;

    public function mount(Product $product): void
    {
        Gate::authorize('stock.view');

        $this->product = $product;
        $this->financialResult = app(ProductFinancialsCalculator::class)->calculate($product);
        $this->externalCostResult = app(ProductExternalRentalCostCalculator::class)->calculate($product);
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name.' — Financials');
    }

    public function with(): array
    {
        return [
            'financials' => $this->financialResult,
            'externalCosts' => $this->externalCostResult,
            'formatter' => app(Formatter::class),
        ];
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
            'revenue_incomplete' => 'Some Asset rental revenue cannot be reliably attributed.',
            'acquisition_incomplete' => 'One or more owned Assets have no valid acquisition cost.',
            'expense_incomplete' => 'One or more operating expenses lack normalized financial data.',
            'currency_unavailable' => 'Owned Assets use incompatible reporting currencies.',
            'usage_incomplete' => 'One or more usage records have an invalid duration.',
            default => 'Some Product financial metrics are not available from the current data.',
        };
    }
}; ?>

<section class="w-full">
    @include('livewire.products.partials.product-header', ['product' => $product, 'subpage' => 'Financials'])
    @include('livewire.products.partials.product-tabs', ['product' => $product, 'activeTab' => 'financials'])

    <div class="space-y-6 px-6 py-4 max-md:px-5 max-sm:px-3">
        @if(!$financials->financialsComplete)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                <div class="font-semibold">Financial history incomplete</div>
                <ul class="mt-1 list-disc space-y-1 pl-5">
                    @foreach(array_unique($financials->unavailableReasons) as $reason)
                        <li>{{ $this->unavailableReason($reason) }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-signals.panel title="Fleet">
            <div class="grid grid-cols-4 gap-4 max-lg:grid-cols-2 max-sm:grid-cols-1">
                @foreach([
                    ['label' => 'Owned Assets', 'value' => $financials->assetCount],
                    ['label' => 'Recovered Assets', 'value' => $financials->recoveredAssetCount],
                    ['label' => 'Below Break-even', 'value' => $financials->belowBreakEvenAssetCount],
                    ['label' => 'Unused Assets', 'value' => $financials->unusedAssetCount],
                    ['label' => 'Incomplete Data', 'value' => $financials->incompleteAssetCount],
                ] as $stat)
                    <div class="rounded-lg border border-[var(--card-border)] px-4 py-3">
                        <div class="text-2xl font-bold text-[var(--text-primary)]" style="font-family: var(--font-display);">{{ $stat['value'] }}</div>
                        <div class="mt-1 text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">{{ $stat['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </x-signals.panel>

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
                            <div class="text-[var(--text-secondary)]">{{ $formatter->money($financials->investmentProfit, $financials->currencyCode) }} above break-even</div>
                        </div>
                    @else
                        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                            <span class="text-[var(--text-secondary)]">{{ $formatter->money($financials->netRentalIncome, $financials->currencyCode) }} of {{ $formatter->money($financials->totalAcquisitionCost, $financials->currencyCode) }} recovered</span>
                            <span class="font-medium text-[var(--text-primary)]">{{ $formatter->money($financials->remainingToBreakEven, $financials->currencyCode) }} remaining to break-even</span>
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
                    ['label' => 'Confirmed Rental Revenue', 'value' => $financials->confirmedAttributedRentalRevenue !== null && ($financials->confirmedAttributedRentalRevenue > 0 || $financials->revenueComplete) ? $formatter->money($financials->confirmedAttributedRentalRevenue, $financials->currencyCode) : '—'],
                    ['label' => 'Operating Expenses', 'value' => $financials->operatingExpenses !== null ? $formatter->money($financials->operatingExpenses, $financials->currencyCode) : '—'],
                    ['label' => 'Net Rental Income', 'value' => $financials->netRentalIncome !== null ? $formatter->money($financials->netRentalIncome, $financials->currencyCode) : '—'],
                ]" />
            </x-signals.panel>

            <x-signals.panel title="Investment">
                <x-signals.data-list layout="vertical" :items="[
                    ['label' => 'Total Acquisition Cost', 'value' => $financials->totalAcquisitionCost !== null ? $formatter->money($financials->totalAcquisitionCost, $financials->currencyCode) : '—'],
                    ['label' => 'Investment Profit', 'value' => $financials->investmentProfit !== null ? $formatter->money($financials->investmentProfit, $financials->currencyCode) : '—'],
                    ['label' => 'Investment ROI', 'value' => $this->formatPercentage($financials->investmentRoi)],
                ]" />
            </x-signals.panel>
        </div>

        <x-signals.panel title="Usage">
            <div class="grid grid-cols-3 gap-4 max-md:grid-cols-1">
                <div class="rounded-lg border border-[var(--card-border)] px-4 py-3"><div class="text-2xl font-bold text-[var(--text-primary)]">{{ $financials->assetParticipationCount }}</div><div class="mt-1 text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">Asset Participations</div></div>
                <div class="rounded-lg border border-[var(--card-border)] px-4 py-3"><div class="text-2xl font-bold text-[var(--text-primary)]">{{ $financials->rentalJobCount }}</div><div class="mt-1 text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">Rental Jobs</div></div>
                <div class="rounded-lg border border-[var(--card-border)] px-4 py-3"><div class="text-2xl font-bold text-[var(--text-primary)]">{{ $financials->actualRentalDays ?? '—' }}</div><div class="mt-1 text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">Asset Rental Days</div></div>
            </div>
        </x-signals.panel>

        @can('costs.view')
            <x-signals.panel title="External Rental Cost">
                @if($externalCosts->unavailableReasons !== [])
                    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                        Actual supplier cost is incomplete because one or more qualifying sub-hire lines have not been fully billed.
                    </div>
                @endif
                <x-signals.data-list layout="vertical" :items="[
                    ['label' => 'Expected Sub-hire Cost', 'value' => $externalCosts->expectedCost !== null ? $formatter->money($externalCosts->expectedCost, $externalCosts->currencyCode) : '—'],
                    ['label' => 'Actual Supplier Cost', 'value' => $externalCosts->actualCost !== null ? $formatter->money($externalCosts->actualCost, $externalCosts->currencyCode) : '—'],
                    ['label' => 'Variance', 'value' => $externalCosts->variance !== null ? $formatter->money($externalCosts->variance, $externalCosts->currencyCode) : '—'],
                    ['label' => 'Completeness', 'value' => $externalCosts->actualCostComplete ? 'Complete' : 'Incomplete'],
                ]" />
                <div class="mt-3 text-xs text-[var(--text-muted)]"><a href="{{ route('supplier-bills.index') }}" wire:navigate.hover class="text-[var(--link)] hover:underline">View supplier bills</a></div>
            </x-signals.panel>
        @endcan

        <x-signals.panel title="Asset Breakdown">
            @if($financials->assetResults === [])
                <x-dense::empty-state title="No eligible owned Assets" description="This Product has no company-owned serialized Assets with financial data." />
            @else
                <div class="s-table-wrap">
                    <table class="s-table w-full">
                        <thead>
                            <tr>
                                <th>Asset</th>
                                <th>Rental Revenue</th>
                                <th>Cost Recovery</th>
                                <th>ROI</th>
                                <th>Participation</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($financials->assetResults as $entry)
                                @php($asset = $entry['asset'])
                                @php($assetFinancials = $entry['financials'])

                                <tr wire:key="product-financial-asset-{{ $asset->id }}">
                                    <td><a href="{{ route('assets.show', $asset) }}" wire:navigate.hover class="font-medium text-(--link) hover:underline">{{ $asset->currentStockLevel?->asset_number ?? 'Asset #'.$asset->id }}</a></td>
                                    <td>{{ $assetFinancials->confirmedAttributedRentalRevenue !== null && ($assetFinancials->revenueComplete || $assetFinancials->confirmedAttributedRentalRevenue > 0) ? $formatter->money($assetFinancials->confirmedAttributedRentalRevenue, $assetFinancials->currencyCode) : '—' }}</td>
                                    <td>{{ $this->formatPercentage($assetFinancials->costRecoveryRatio) }}</td>
                                    <td>{{ $this->formatPercentage($assetFinancials->investmentRoi) }}</td>
                                    <td>{{ $assetFinancials->rentalCount }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-signals.panel>
    </div>
</section>
