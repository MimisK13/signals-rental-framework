<?php

use App\Models\Asset;
use App\Services\Assets\AssetUtilizationCalculator;
use App\Services\Assets\AssetUtilizationResult;
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
}; ?>

<section class="w-full">
    <x-dense::page-header :title="$asset->product?->name ?? 'Asset'" :subtitle="'Utilization'">
        <x-slot:meta>
            <span class="text-sm text-[var(--text-muted)]">{{ $asset->currentStockLevel?->asset_number ?? 'Serialized Asset' }}</span>
        </x-slot:meta>
    </x-dense::page-header>

    <div class="space-y-6 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="s-panel">
            <div class="s-panel-body grid gap-4 md:grid-cols-2 md:items-end">
                <flux:input wire:model.live.debounce.300ms="from" type="datetime-local" label="From" />
                <flux:input wire:model.live.debounce.300ms="until" type="datetime-local" label="Until" />
            </div>
            @if($this->periodError())
                <div class="px-4 pb-4 text-sm text-red-700 dark:text-red-300">{{ $this->periodError() }}</div>
            @endif
        </div>

        @if($this->result)
            @php($result = $this->result)
            @if($result->completeness->value !== 'complete')
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                    <div class="font-semibold">Utilization {{ $result->completeness->label() }}</div>
                    <ul class="mt-1 list-disc space-y-1 pl-5">
                        @forelse($result->unavailableReasons as $reason)
                            <li>{{ $this->reason($reason) }}</li>
                        @empty
                            <li>The utilization denominator is unavailable.</li>
                        @endforelse
                    </ul>
                </div>
            @endif

            <x-signals.panel title="Actual Utilization">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div><div class="text-2xl font-bold">{{ $this->percentage($result->actualRentalUtilizationRatio) }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Rental Utilization</div></div>
                    <div><div class="text-2xl font-bold">{{ $this->duration($result->plannableDuration) }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Plannable Time</div></div>
                    <div><div class="text-2xl font-bold">{{ $this->duration($result->actualRentalDuration) }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Actual Rental Time</div></div>
                    <div><div class="text-2xl font-bold">{{ $this->duration($result->idlePlannableDuration) }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Idle Time</div></div>
                </div>
                <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div><div class="font-semibold">{{ $this->duration($result->maintenanceBlockedDuration) }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Maintenance Blocked</div></div>
                    <div><div class="font-semibold">{{ $this->duration($result->lifecycleBlockedDuration) }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Lifecycle Blocked</div></div>
                    <div><div class="font-semibold">{{ $this->duration($result->ownedDuration) }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Owned Time</div></div>
                    <div><div class="font-semibold">{{ $result->rentalParticipationCount }}</div><div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">Rental Participations</div></div>
                </div>
            </x-signals.panel>
        @endif
    </div>
</section>
