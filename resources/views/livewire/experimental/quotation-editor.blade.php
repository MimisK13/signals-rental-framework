@php
    $opp = $this->opportunity;
    $formatter = app(\App\Support\Formatter::class);
    $currencySymbol = $formatter->currencySymbol($opp->currency_code ?? settings('company.base_currency', 'GBP'));
    $dealTotalRaw = $opp->deal_total !== null ? $opp->formatMoneyCost('deal_total') : '';
    $echoChannel = 'availability.opportunity.'.$opp->id;
    $chargeTotalMinor = (int) ($opp->charge_total ?? 0);
    $pricingFrozen = $opp->pricingFrozen();
    $priceLocked = $opp->hasLocks();
    $canManagePriceLock = Gate::allows('opportunities.unlock_rates');
@endphp

<div
    class="opportunity-line-items-editor w-full"
    wire:ignore
    x-data="window.signals.quotationEditor({ oppId: {{ $opp->id }}, currencySymbol: @js($currencySymbol) })"
    x-init="boot()"
    x-on:line-items-mutation-done.window="onMutationDone($event)"
    x-on:opportunity-lifecycle-changed.window="onLifecycleChanged($event)"
    data-server-charge-total-minor="{{ $chargeTotalMinor }}"
>
    {{--
        Frozen config island — first write wins; never re-emitted into x-data.
        Keeping volatile Livewire values OUT of the x-data expression prevents
        Livewire morph from re-initialising Alpine (which left wire:ignore tbody
        rows with dead click bindings after in-session adds).
    --}}
    <script wire:ignore data-lf-seed>
        (function () {
            window.__lfExperimentalQuotationEditorSeed = window.__lfExperimentalQuotationEditorSeed || {};
            window.__lfExperimentalQuotationEditorConfig = window.__lfExperimentalQuotationEditorConfig || {};
            var key = {{ $opp->id }};
            if (!(key in window.__lfExperimentalQuotationEditorSeed)) {
                window.__lfExperimentalQuotationEditorSeed[key] = {
                    tree: @js($this->tree),
                    revision: @js((string) $this->treeRevision()),
                };
            }
            if (!(key in window.__lfExperimentalQuotationEditorConfig)) {
                window.__lfExperimentalQuotationEditorConfig[key] = {
                    csrfToken: @js(csrf_token()),
                    editable: @js($editable),
                    fieldsEditable: @js($fieldsEditable),
                    pricingFrozen: @js($pricingFrozen),
                    priceLocked: @js($priceLocked),
                    canManagePriceLock: @js($canManagePriceLock),
                    currencySymbol: @js($currencySymbol),
                    echoChannel: @js($echoChannel),
                    destinations: @js($this->destinations),
                    sectionOptions: @js($this->sectionOptions),
                    serverChargeTotalMinor: @js($chargeTotalMinor),
                    dealTotalRaw: @js($dealTotalRaw),
                    hasDealPrice: @js($opp->deal_total !== null),
                    dealPriceMode: @js($opp->deal_price_mode ?? 'flat_headline'),
                    serverDealTotalMinor: @js($opp->deal_total !== null ? (int) $opp->deal_total : null),
                    collapsedSectionIds: @js($collapsedSectionIds),
                };
            }
        })();
    </script>

    <div class="flex min-w-0 items-stretch">
        <div class="min-w-0 flex-1">
            {{-- Toolbar: quick-add, section actions, sync + deal price --}}
            @include('livewire.experimental.quotation-editor.toolbar')

            @if($opp->pricingFrozen() && ! $editable)
                <p class="text-sm text-[var(--text-muted)] mb-3">
                    @if($opp->deal_total !== null)
                        Deal price:
                        <strong class="font-mono">
                            {{ $formatter->money($opp->deal_total, $opp->currency_code ?? settings('company.base_currency', 'GBP')) }}
                        </strong>
                    @elseif($opp->hasLocks())
                        Pricing is frozen — rates and tax are locked on this opportunity.
                    @endif
                </p>
            @endif

            @include('livewire.experimental.quotation-editor.grid')
        </div>

        @include('livewire.experimental.quotation-editor.group-panel')
    </div>

    @include('livewire.experimental.quotation-editor.overlays')
    @include('livewire.experimental.quotation-editor.modals')
</div>
