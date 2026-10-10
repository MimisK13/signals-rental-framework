<?php

use App\Models\Asset;
use App\Services\Investment\AssetInvestmentIntelligenceReader;
use App\Services\Investment\AssetInvestmentIntelligenceResult;
use App\Support\Formatter;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    public Asset $asset;

    #[Url(as: 'from')]
    public string $from = '';

    #[Url(as: 'until')]
    public string $until = '';

    public string $preset = '12m';

    public function mount(Asset $asset): void
    {
        Gate::authorize('stock.view');
        $this->asset = $asset->load(['product', 'currentStockLevel']);
        $this->until = $this->until !== '' ? $this->until : CarbonImmutable::now('UTC')->format('Y-m-d\TH:i');
        $this->from = $this->from !== '' ? $this->from : CarbonImmutable::now('UTC')->subYear()->format('Y-m-d\TH:i');
    }

    public function rendering(View $view): void
    {
        $view->title($this->asset->product?->name.' — Investment');
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
    public function result(): ?AssetInvestmentIntelligenceResult
    {
        if ($this->periodError() !== null) {
            return null;
        }

        $asOf = CarbonImmutable::now('UTC');

        return app(AssetInvestmentIntelligenceReader::class)->read(
            $this->asset,
            CarbonImmutable::parse($this->from)->utc(),
            CarbonImmutable::parse($this->until)->utc(),
            $asOf,
        );
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
}; ?>

<section class="w-full">
    <x-signals.page-header :title="$asset->product?->name ?? 'Asset'" subtitle="Investment">
        <x-slot:meta><span class="text-sm text-[var(--text-muted)]">{{ $asset->currentStockLevel?->asset_number ?? 'Serialized Asset' }}</span></x-slot:meta>
    </x-signals.page-header>

    <div class="space-y-6 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="s-panel">
            <div class="s-panel-body flex flex-wrap gap-2">
                <flux:button wire:click="setPreset('90d')" variant="{{ $preset === '90d' ? 'primary' : 'ghost' }}">90 Days</flux:button>
                <flux:button wire:click="setPreset('ytd')" variant="{{ $preset === 'ytd' ? 'primary' : 'ghost' }}">Year to Date</flux:button>
                <flux:button wire:click="setPreset('12m')" variant="{{ $preset === '12m' ? 'primary' : 'ghost' }}">Trailing 12 Months</flux:button>
            </div>
            <div class="s-panel-body grid gap-4 border-t border-[var(--card-border)] md:grid-cols-2">
                <flux:input wire:model.live.debounce.300ms="from" type="datetime-local" label="From" />
                <flux:input wire:model.live.debounce.300ms="until" type="datetime-local" label="Until" />
            </div>
            @if($this->periodError())<div class="px-4 pb-4 text-sm text-red-700 dark:text-red-300">{{ $this->periodError() }}</div>@endif
        </div>

        @if($this->result)
            @php($result = $this->result)
            <x-signals.panel title="Investment Position">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div><div class="text-2xl font-bold">{{ $this->money($result->financials->netRentalIncome, $result->financials->currencyCode) }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Net Rental Income</div></div>
                    <div><div class="text-2xl font-bold">{{ $this->percentage($result->financials->costRecoveryRatio) }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Cost Recovery</div></div>
                    <div><div class="text-2xl font-bold">{{ $this->percentage($result->financials->investmentRoi) }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Investment ROI</div></div>
                    <div><div class="text-2xl font-bold">{{ $this->money($result->lifecycleInvestmentProfit, $result->financials->currencyCode) }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Lifecycle Profit</div></div>
                    <div><div class="text-2xl font-bold">{{ $this->percentage($result->lifecycleRoi) }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Lifecycle ROI</div></div>
                </div>
            </x-signals.panel>

            <x-signals.panel title="Operating Evidence">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div><div class="text-2xl font-bold">{{ $this->percentage($result->utilization->actualRentalUtilizationRatio) }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Actual Utilization</div></div>
                    <div><div class="font-semibold">{{ intdiv($result->utilization->plannableDuration ?? 0, 3600) }} h</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Plannable Time</div></div>
                    <div><div class="font-semibold">{{ $this->percentage($result->utilization->maintenanceDowntimeRatio) }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Maintenance Burden</div></div>
                    <div><div class="font-semibold">{{ $result->utilization->rentalParticipationCount }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Participations</div></div>
                    <div><div class="font-semibold">{{ $result->lifecycleSummary['status'] }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Lifecycle</div></div>
                </div>
            </x-signals.panel>

            <x-signals.panel title="Signals & Guidance">
                <div class="space-y-4">
                    <div class="flex flex-wrap gap-2">@forelse($result->signals as $signal)<span class="s-badge s-badge-amber">{{ $signal->type->label() }}</span>@empty<span class="text-sm text-[var(--text-muted)]">No investment signals were triggered.</span>@endforelse</div>
                    <div class="text-sm font-semibold">{{ collect($result->guidance)->map(fn($guidance) => $guidance->label())->implode(', ') }}</div>
                    @foreach($result->signals as $signal)
                        <div class="rounded-lg border border-[var(--card-border)] p-4">
                            <div class="font-semibold">{{ $signal->type->label() }}</div>
                            <div class="mt-1 text-sm text-[var(--text-secondary)]">{{ $signal->explanation }}</div>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-[var(--text-muted)]">@foreach($signal->evidence as $evidence)<li>{{ $evidence->metric }}: {{ $evidence->observed }} @if($evidence->operator)({{ $evidence->operator }} {{ $evidence->threshold }})@endif</li>@endforeach</ul>
                        </div>
                    @endforeach
                    @if($result->completeness->reasons !== [])<div class="text-sm text-amber-700 dark:text-amber-300">Limitations: {{ implode('; ', $result->completeness->reasons) }}</div>@endif
                </div>
            </x-signals.panel>
        @endif
    </div>
</section>
