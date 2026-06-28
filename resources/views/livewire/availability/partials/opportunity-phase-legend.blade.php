{{-- Opportunity phase key entries (Delivery / In-Use / Collection) from OpportunityPresentation. --}}
@if(count($entries ?? []) > 0)
    @foreach($entries as $entry)
        <span class="flex items-center gap-1.5" wire:key="phase-legend-{{ $loop->index }}">
            <span class="inline-block h-3 w-4 rounded-sm" style="background: {{ $entry['colour'] }};"></span>
            {{ $entry['label'] }}
        </span>
    @endforeach
@endif
