
<section class="w-full">
    @include('project-opportunities::partials.opportunity-header', ['opportunity' => $opportunity, 'showActions' => true, 'canChangeStatus' => $canChangeStatus])

    @include('project-opportunities::partials.opportunity-tabs', ['opportunity' => $opportunity, 'activeTab' => 'overview'])

    @if(($canGenerateStandard ?? false) || ($canGenerateDeposit ?? false))
        <div class="px-6 pt-4 flex flex-wrap gap-2 max-md:px-5 max-sm:px-3">
            @if($canGenerateStandard)
                <button type="button" wire:click="generateInvoice" class="s-btn s-btn-sm s-btn-accent">Invoice</button>
            @endif
            @if($canGenerateDeposit)
                <button type="button" wire:click="generateDeposit" class="s-btn s-btn-sm s-btn-ghost">Generate Deposit</button>
            @endif
            <a href="{{ route('opportunities.invoices', $opportunity) }}" wire:navigate.hover class="s-btn s-btn-sm s-btn-ghost">View Invoices</a>
        </div>
    @elseif($fullyInvoiced ?? false)
        <div class="px-6 pt-4 max-md:px-5 max-sm:px-3">
            <a href="{{ route('opportunities.invoices', $opportunity) }}" wire:navigate.hover class="text-sm text-[var(--link)] hover:underline">View invoices</a>
        </div>
    @endif

    {{-- Archived (soft-deleted) opportunities are viewable but read-only. Surface a
         clear banner + a Restore action; the transition actions/status picker are
         suppressed in with() while archived. --}}
    @if($isArchived)
        <div class="px-6 pt-4 max-md:px-5 max-sm:px-3">
            <x-signals.alert type="warning">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span>{{ __('This opportunity is archived. It is read-only until restored.') }}</span>
                    @if($canRestore)
                        <button type="button"
                                wire:click="restore"
                                wire:confirm="{{ __('Restore this opportunity? It will become active and editable again.') }}"
                                class="s-btn s-btn-sm s-btn-outline-green shrink-0">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                            {{ __('Restore') }}
                        </button>
                    @endif
                </div>
            </x-signals.alert>
        </div>
    @endif

    @if(session('error'))
        <div class="px-6 pt-4 max-md:px-5 max-sm:px-3">
            <x-signals.alert type="danger">{{ session('error') }}</x-signals.alert>
        </div>
    @endif

    @php
        $formatter = app(\App\Support\Formatter::class);
        $stateBadgeClass = match($opportunity->state) {
            \App\Domain\Projects\Opportunities\Enums\OpportunityState::Draft => 's-badge-zinc',
            \App\Domain\Projects\Opportunities\Enums\OpportunityState::Quotation => 's-badge-blue',
            \App\Domain\Projects\Opportunities\Enums\OpportunityState::Order => 's-badge-green',
            default => 's-badge-zinc',
        };
        $status = $opportunity->statusEnum();

        // Active quote version (if the opportunity has been split into versions).
        // Surfaced under "Number" in Key Attributes as `v{n}` + its timestamp.
        $activeVersion = $opportunity->activeVersion;
        $versionRow = $activeVersion
            ? ['label' => 'Version', 'value' => 'v'.$activeVersion->version_number.($activeVersion->created_at ? ' · '.$activeVersion->created_at->format('d M Y') : '')]
            : null;
    @endphp

    {{-- 2-column layout: ~25% Key-Attributes sidebar + ~75% live line-item editor.
         Uses standard grid utilities (lg:grid-cols-4 + col-span 1/3) which are
         guaranteed to be present in the compiled Tailwind build, rather than an
         arbitrary grid-template that may be purged. --}}
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 px-6 py-4 max-md:px-5 max-sm:px-3">

        {{-- ============================================================ --}}
        {{-- LEFT SIDEBAR — totals, key attributes, dates, member         --}}
        {{-- ============================================================ --}}
        <div class="min-w-0 space-y-6 lg:col-span-1">
            {{-- Compact totals stacked ABOVE Key Attributes (the live ex-tax breakdown
                 also renders in the editor footer; this keeps the headline figures
                 visible alongside attributes). --}}
            <x-signals.panel title="Totals">
                <x-signals.data-list layout="vertical" :items="[
                    ['label' => 'Charge Total', 'value' => $formatter->money($opportunity->charge_total ?? 0), 'mono' => true],
                    ['label' => 'Excl. Tax', 'value' => $formatter->money($opportunity->charge_excluding_tax_total ?? 0), 'mono' => true],
                    ['label' => 'Tax', 'value' => $formatter->money($opportunity->tax_total ?? 0), 'mono' => true],
                ]" />
            </x-signals.panel>

            <x-signals.panel title="Dates">
                <x-signals.data-list layout="vertical" :items="[
                    ['label' => 'Starts', 'value' => $opportunity->starts_at ? $formatter->dateTime($opportunity->starts_at) : '—'],
                    ['label' => 'Ends', 'value' => $opportunity->ends_at ? $formatter->dateTime($opportunity->ends_at) : '—'],
                    ['label' => 'Charge Starts', 'value' => $opportunity->charge_starts_at ? $formatter->dateTime($opportunity->charge_starts_at) : '—'],
                    ['label' => 'Charge Ends', 'value' => $opportunity->charge_ends_at ? $formatter->dateTime($opportunity->charge_ends_at) : '—'],
                ]" />
            </x-signals.panel>

            <x-signals.panel title="Key Attributes">
                <x-signals.data-list layout="vertical" :items="array_filter([
                    $opportunity->number ? ['label' => 'Number', 'value' => $opportunity->number, 'mono' => true] : null,
                    $versionRow,
                    $opportunity->reference ? ['label' => 'Reference', 'value' => $opportunity->reference] : null,
                    ['label' => 'State', 'value' => $opportunity->state->label(), 'badge' => $stateBadgeClass],
                    ['label' => 'Status', 'value' => $status->label()],
                    ['label' => 'Currency', 'value' => $opportunity->currency_code ?? '—'],
                    ['label' => 'Created', 'value' => $opportunity->created_at?->format('d M Y') ?? '—'],
                    ['label' => 'Updated', 'value' => $opportunity->updated_at?->format('d M Y') ?? '—'],
                ])" />
            </x-signals.panel>

            <x-signals.panel title="Member & Store">
                <x-signals.data-list layout="vertical" :items="array_filter([
                    $opportunity->member
                        ? ['label' => 'Member', 'value' => $opportunity->member->name, 'href' => route('members.show', $opportunity->member)]
                        : ['label' => 'Member', 'value' => '—'],
                    ['label' => 'Venue', 'value' => $opportunity->venue?->name ?? '—'],
                    ['label' => 'Store', 'value' => $opportunity->store?->name ?? '—'],
                    ['label' => 'Owner', 'value' => $opportunity->owner?->name ?? '—'],
                ])" />
            </x-signals.panel>
        </div>

        {{-- ============================================================ --}}
        {{-- MAIN — the live line-item editor (nested Volt component) --}}
        {{-- ============================================================ --}}
        <div class="min-w-0 lg:col-span-3">
            <livewire:opportunities.line-items :opportunity="$opportunity" :key="'opp-line-items-'.$opportunity->id" />
        </div>
    </div>

    @include('project-opportunities::partials.opportunity-action-modals')
</section>
