<?php

declare(strict_types=1);

namespace App\Domain\Assets\Presentation\Livewire;

use App\Domain\Products\Models\Product;
use App\Domain\Stock\Enums\StockMethod;
use App\Services\Assets\ProductUtilizationCalculator;
use App\Services\Assets\ProductUtilizationResult;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

#[Layout('components.layouts.app')]
final class ProductUtilization extends Component
{
    public Product $product;

    #[Url(as: 'from')]
    public string $from = '';

    #[Url(as: 'until')]
    public string $until = '';

    public function mount(Product $product): void
    {
        Gate::authorize('stock.view');
        abort_unless($product->stock_method === StockMethod::Serialised, 404);
        $this->product = $product;
        $this->from = $this->from !== '' ? $this->from : CarbonImmutable::now('UTC')->subDays(30)->startOfDay()->format('Y-m-d\\TH:i');
        $this->until = $this->until !== '' ? $this->until : CarbonImmutable::now('UTC')->format('Y-m-d\\TH:i');
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name.' — Utilization');
    }

    #[Computed]
    public function result(): ?ProductUtilizationResult
    {
        if ($this->periodError() !== null) {
            return null;
        }

        return app(ProductUtilizationCalculator::class)->calculate(
            $this->product,
            CarbonImmutable::parse($this->from),
            CarbonImmutable::parse($this->until),
            CarbonImmutable::now('UTC'),
        );
    }

    public function periodError(): ?string
    {
        try {
            $from = CarbonImmutable::parse($this->from);
            $until = CarbonImmutable::parse($this->until);
        } catch (Throwable) {
            return 'Enter a valid utilization period.';
        }

        return $until->greaterThan($from) ? null : 'The end of the period must be after its start.';
    }

    public function duration(int $seconds): string
    {
        return intdiv($seconds, 3600).' h';
    }

    public function percentage(?string $ratio): string
    {
        return $ratio === null
            ? '—'
            : BigDecimal::of($ratio)->multipliedBy('100')->toScale(2, RoundingMode::HALF_UP)->__toString().'%';
    }

    public function render(): View
    {
        return view('assets::product-utilization');
    }
}
