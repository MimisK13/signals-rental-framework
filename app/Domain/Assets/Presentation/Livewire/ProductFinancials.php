<?php

declare(strict_types=1);

namespace App\Domain\Assets\Presentation\Livewire;

use App\Domain\Products\Models\Product;
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
use Livewire\Component;

#[Layout('components.layouts.app')]
final class ProductFinancials extends Component
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

    /** @return array<string, mixed> */
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

    public function render(): View
    {
        return view('assets::product-financials');
    }
}
