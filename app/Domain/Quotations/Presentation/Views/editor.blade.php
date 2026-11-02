@php
    $formatter = app(\App\Support\Formatter::class);
    $currencySymbol = $formatter->currencySymbol($quotation->currency_code);
@endphp

<section class="relative min-w-0 w-full">
    <x-dense::page-header :title="$quotation->number . ' · ' . $quotation->subject">
        <x-slot:meta>
            <x-dense::status variant="outline" :color="match ($quotation->status->value) {
                'accepted' => 'green',
                'sent' => 'blue',
                'declined', 'cancelled', 'expired' => 'red',
                default => 'amber',
            }" :label="$quotation->status->label()" />
            <span>{{ $quotation->project?->name ?? 'Standalone' }}</span>
            <span>Version {{ $activeVersion->version_number }}</span>
        </x-slot:meta>
        <x-slot:actions>
            <x-dense::button :href="route('quotations.index')" variant="ghost" size="sm" wire:navigate.hover>Back</x-dense::button>
        </x-slot:actions>
    </x-dense::page-header>

    <div class="px-6 pb-6 max-md:px-5 max-sm:px-3">
        <div
            class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-[var(--card-border)] bg-[var(--card-bg)] p-3">
            <label for="quotation-version"
                class="text-xs font-semibold uppercase tracking-wide text-[var(--text-muted)]">Version</label>
            <x-dense::select id="quotation-version" class="w-48" wire:change="activateVersion($event.target.value)"
                :disabled="$quotation->status->isTerminal()">
                @foreach ($versions as $versionOption)
                    <option value="{{ $versionOption->id }}" @selected($versionOption->id === $activeVersion->id)>
                        v{{ $versionOption->version_number }} ·
                        {{ $versionOption->label ?? $versionOption->version_type->label() }} ·
                        {{ $versionOption->status->label() }}
                    </option>
                @endforeach
            </x-dense::select>

            @can('update', $quotation)
                @unless ($quotation->status->isTerminal())
                    <x-dense::button type="button" variant="ghost" size="sm" wire:click="createVersion('revision')">New
                        revision</x-dense::button>
                    <x-dense::button type="button" variant="ghost" size="sm" wire:click="createVersion('alternative')">New
                        alternative</x-dense::button>
                @endunless
            @endcan

            @if ($activeVersion->status === \App\Domain\Quotations\Enums\QuotationVersionStatus::Draft)
                @can('update', $quotation)
                    <div class="flex items-center gap-2 border-l border-[var(--card-border)] pl-2">
                        <x-dense::input class="w-28 font-mono" wire:model="dealPriceInput" placeholder="Deal total"
                            aria-label="Deal total" />
                        <x-dense::select class="w-36" wire:model="dealPriceMode" aria-label="Deal price mode">
                            <option value="flat_headline">Flat headline</option>
                            <option value="proportional">Proportional</option>
                        </x-dense::select>
                        <x-dense::button type="button" variant="secondary" size="sm" wire:click="applyDealPrice">Apply
                            deal</x-dense::button>
                        @if ($quotation->deal_total !== null)
                            <x-dense::button type="button" variant="ghost" size="sm" wire:click="clearDealPrice">Clear
                                deal</x-dense::button>
                        @endif
                    </div>
                @endcan
            @endif

            <div class="ml-auto flex items-center gap-2">
                @if ($activeVersion->status === \App\Domain\Quotations\Enums\QuotationVersionStatus::Draft)
                    @can('send', $quotation)
                        <x-dense::button type="button" variant="secondary" size="sm"
                            wire:click="sendActiveVersion">Send</x-dense::button>
                    @endcan
                @endif

                @if (in_array(
                        $activeVersion->status,
                        [
                            \App\Domain\Quotations\Enums\QuotationVersionStatus::Draft,
                            \App\Domain\Quotations\Enums\QuotationVersionStatus::Sent,
                        ],
                        true))
                    @can('accept', $quotation)
                        <x-dense::button type="button" variant="accent" size="sm" wire:click="acceptActiveVersion"
                            wire:confirm="Accept this quotation version and continue to its Project?">Accept</x-dense::button>
                    @endcan
                    @can('update', $quotation)
                        <x-dense::button type="button" variant="ghost" size="sm" wire:click="declineActiveVersion"
                            wire:confirm="Decline this quotation version?">Decline</x-dense::button>
                    @endcan
                @endif

                @if ($quotation->project !== null)
                    <x-dense::button :href="route('projects.show', $quotation->project)" variant="ghost" size="sm" wire:navigate.hover>Open
                        Project</x-dense::button>
                @endif
            </div>
        </div>

        <div class="quotation-line-items-editor w-full" wire:ignore x-data="window.signals.canonicalQuotationEditor({ quotationId: {{ $quotation->id }}, currencySymbol: @js($currencySymbol) })" x-init="boot()"
            x-on:line-items-mutation-done.window="onMutationDone($event)"
            data-server-charge-total-minor="{{ $quotation->charge_total }}">
            <script wire:ignore data-lf-seed>
                (function() {
                    window.__lfQuotationEditorSeed = window.__lfQuotationEditorSeed || {};
                    window.__lfQuotationEditorConfig = window.__lfQuotationEditorConfig || {};
                    var key = {{ $quotation->id }};
                    if (!(key in window.__lfQuotationEditorSeed)) {
                        window.__lfQuotationEditorSeed[key] = {
                            tree: @js($tree),
                            revision: @js((string) $quotation->editor_revision),
                        };
                    }
                    if (!(key in window.__lfQuotationEditorConfig)) {
                        window.__lfQuotationEditorConfig[key] = {
                            csrfToken: @js(csrf_token()),
                            editable: @js($editable),
                            fieldsEditable: @js($fieldsEditable),
                            pricingFrozen: false,
                            priceLocked: false,
                            canManagePriceLock: false,
                            currencySymbol: @js($currencySymbol),
                            echoChannel: '',
                            destinations: @js($destinations),
                            sectionOptions: @js($destinations),
                            serverChargeTotalMinor: @js((int) $quotation->charge_total),
                            dealTotalRaw: @js($quotation->deal_total !== null ? $quotation->formatMoneyCost('deal_total') : ''),
                            hasDealPrice: @js($quotation->deal_total !== null),
                            dealPriceMode: @js($quotation->deal_price_mode->value),
                            serverDealTotalMinor: @js($quotation->deal_total),
                            collapsedSectionIds: @js($collapsedSectionIds),
                        };
                    }
                })();
            </script>

            <div class="flex min-w-0 items-stretch">
                <div class="min-w-0 flex-1">
                    @include('quotations::editor.toolbar')
                    @include('quotations::editor.grid')
                </div>

                @include('quotations::editor.group-panel')
            </div>

            @include('quotations::editor.overlays')
            @include('quotations::editor.modals')
        </div>
    </div>
</section>
