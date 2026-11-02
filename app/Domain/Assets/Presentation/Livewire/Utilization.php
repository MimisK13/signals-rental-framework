<?php

declare(strict_types=1);

namespace App\Domain\Assets\Presentation\Livewire;

use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Services\AssetUtilizationCalculator;
use App\Domain\Assets\Services\AssetUtilizationResult;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

#[Layout('components.layouts.app')]
final class Utilization extends Component
{
    public Asset $asset;

    #[Url(as: 'from')]
    public string $from = '';

    #[Url(as: 'until')]
    public string $until = '';

    public function mount(Asset $asset): void
    {
        Gate::authorize('stock.view');
        $this->asset = $asset->load(['product', 'currentStockLevel']);
        $this->from = $this->from !== '' ? $this->from : CarbonImmutable::now('UTC')->subDays(30)->startOfDay()->format('Y-m-d\\TH:i');
        $this->until = $this->until !== '' ? $this->until : CarbonImmutable::now('UTC')->format('Y-m-d\\TH:i');
    }

    public function rendering(View $view): void
    {
        $view->title($this->asset->product?->name.' — Utilization');
    }

    #[Computed]
    public function result(): ?AssetUtilizationResult
    {
        if ($this->periodError() !== null) {
            return null;
        }

        return app(AssetUtilizationCalculator::class)->calculate(
            $this->asset,
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

    public function duration(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $hours.' h'.($minutes > 0 ? ' '.$minutes.' m' : '');
    }

    public function percentage(?string $ratio): string
    {
        return $ratio === null
            ? '—'
            : BigDecimal::of($ratio)->multipliedBy('100')->toScale(2, RoundingMode::HALF_UP)->__toString().'%';
    }

    public function reason(string $reason): string
    {
        return match ($reason) {
            'acquisition_unknown' => 'Acquisition date is not recorded.',
            'lifecycle_history_incomplete' => 'Lifecycle history does not cover the full period.',
            'usage_incomplete' => 'One or more usage records are incomplete.',
            'overlapping_usage' => 'Overlapping usage records were unioned.',
            'no_plannable_time' => 'There is no plannable time in this period.',
            'ownership_ineligible' => 'Utilization is limited to company-owned serialized Assets.',
            default => 'Some utilization data is incomplete.',
        };
    }

    public function render(): ViewContract
    {
        return view('assets::utilization');
    }
}
