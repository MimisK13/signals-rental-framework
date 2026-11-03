<?php

declare(strict_types=1);

namespace App\Domain\Products\Presentation\Livewire;

use App\Domain\Products\Investment\Services\ProductInvestmentIntelligenceReader;
use App\Domain\Products\Investment\Services\ProductInvestmentIntelligenceResult;
use App\Domain\Products\Models\Product;
use App\Support\Formatter;
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

#[Layout('components.layouts.app')] final class Investment extends Component
{
    public Product $product;

    #[Url(as: 'from')]
    public string $from = '';

    #[Url(as: 'until')]
    public string $until = '';

    public string $preset = '12m';

    public function mount(Product $product): void
    {
        Gate::authorize('stock.view');
        $this->product = $product;
        $this->until = $this->until !== '' ? $this->until : CarbonImmutable::now('UTC')->format('Y-m-d\TH:i');
        $this->from = $this->from !== '' ? $this->from : CarbonImmutable::now('UTC')->subYear()->format('Y-m-d\TH:i');
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name.' — Investment');
    }

    public function setPreset(string $preset): void
    {
        $now = CarbonImmutable::now('UTC');
        $this->preset = $preset;
        $this->until = $now->format('Y-m-d\TH:i');
        $this->from = match ($preset) {
            '90d' => $now->subDays(90)->format('Y-m-d\TH:i'),
            'ytd' => $now->startOfYear()->format('Y-m-d\TH:i'),
            default => $now->subYear()->format('Y-m-d\TH:i'),
        };
    }

    #[Computed]
    public function result(): ?ProductInvestmentIntelligenceResult
    {
        if ($this->periodError() !== null) {
            return null;
        }

        return app(ProductInvestmentIntelligenceReader::class)->read($this->product, CarbonImmutable::parse($this->from)->utc(), CarbonImmutable::parse($this->until)->utc(), CarbonImmutable::now('UTC'));
    }

    public function periodError(): ?string
    {
        try {
            $from = CarbonImmutable::parse($this->from);
            $until = CarbonImmutable::parse($this->until);
        } catch (Throwable) {
            return 'Enter a valid investment period.';
        }

        return $until->greaterThan($from) ? null : 'The end of the period must be after its start.';
    }

    public function percentage(?string $ratio): string
    {
        return $ratio === null ? '—' : BigDecimal::of($ratio)->multipliedBy('100')->toScale(2, RoundingMode::HALF_UP)->__toString().'%';
    }

    public function money(?int $amount, string $currency): string
    {
        return $amount === null ? '—' : app(Formatter::class)->money($amount, $currency);
    }

    public function render(): View
    {
        return view('products::investment');
    }
}
