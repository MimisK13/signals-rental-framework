{{-- Bookings grid body + legends (shown when grid is ready / not loading). --}}
@include('livewire.availability.partials.bookings-grid')
@include('livewire.availability.partials.grid-legend')
<div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[11px] text-[var(--text-muted)]">
    <span class="font-semibold uppercase tracking-wide">{{ __('Opportunity phases') }}</span>
    @include('livewire.availability.partials.opportunity-phase-legend', [
        'entries' => $this->opportunityPhaseLegend,
    ])
</div>
