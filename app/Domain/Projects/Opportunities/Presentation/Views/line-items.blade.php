
@php
    $opp = $this->opportunity;
    $formatter = app(\App\Support\Formatter::class);
    $currencySymbol = trim(preg_replace('/[0-9.,\s]/u', '', $formatter->money(0, $opp->currency_code ?? settings('company.base_currency', 'GBP'))));
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
    x-data="window.signals.lineItemsEditor({ oppId: {{ $opp->id }} })"
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
            window.__lfSeed = window.__lfSeed || {};
            window.__lfEditorConfig = window.__lfEditorConfig || {};
            var key = {{ $opp->id }};
            if (!(key in window.__lfSeed)) {
                window.__lfSeed[key] = {
                    tree: @js($this->tree),
                    revision: @js((string) $this->treeRevision()),
                    cacheToken: @js($opp->state->value.':'.$opp->status),
                };
            }
            if (!(key in window.__lfEditorConfig)) {
                window.__lfEditorConfig[key] = {
                    csrfToken: @js(csrf_token()),
                    editable: @js($editable),
                    fieldsEditable: @js($fieldsEditable),
                    pricingFrozen: @js($pricingFrozen),
                    priceLocked: @js($priceLocked),
                    canManagePriceLock: @js($canManagePriceLock),
                    catalogue: @js($catalogue),
                    currencySymbol: @js($currencySymbol),
                    echoChannel: @js($echoChannel),
                    destinations: @js($this->destinations),
                    sectionOptions: @js($this->sectionOptions),
                    serverChargeTotalMinor: @js($chargeTotalMinor),
                    dealTotalRaw: @js($dealTotalRaw),
                    hasDealPrice: @js($opp->deal_total !== null),
                    dealPriceMode: @js($opp->deal_price_mode ?? 'flat_headline'),
                    serverDealTotalMinor: @js($opp->deal_total !== null ? (int) $opp->deal_total : null),
                };
            }
        })();
    </script>

    {{-- Toolbar: quick-add, section actions, sync + deal price --}}
    <div class="lf-toolbar-sticky flex flex-wrap items-center gap-2 mb-3">
        @if($editable)
            <div class="lf-quick-add-bar">
                <div class="lf-quick-add-input-group">
                    <span class="lf-quick-add-prefix" aria-hidden="true">+</span>
                    <div class="relative flex-1 min-w-0">
                        <input
                            type="text"
                            class="s-input w-full"
                            placeholder="Quick add — &ldquo;6 spider&rdquo;, or a SKU, then Enter"
                            autocomplete="off"
                            x-model="quickAddQuery"
                            x-ref="quickAddInput"
                            x-on:input="onPickerInput($refs.quickAddInput, quickAddQuery, true)"
                            x-on:keydown="onQuickAddInputKeydown($event, $refs.quickAddInput)"
                            x-on:focus="onPickerInput($refs.quickAddInput, quickAddQuery, true)"
                            x-on:blur="closePickerSoon()"
                            x-bind:disabled="mutationPending"
                        >
                        <span class="text-xs text-[var(--text-faint)] font-mono" x-show="quickAddQtyHint" x-text="quickAddQtyHint"></span>
                    </div>
                </div>
                <label class="text-xs text-[var(--text-faint)]">qty</label>
                <input
                    type="number"
                    min="1"
                    step="1"
                    placeholder="1"
                    class="s-input text-center font-mono lf-quick-add-qty"
                    x-ref="quickAddQty"
                    x-model="quickAddQty"
                    x-on:keydown="onQuickAddQtyKeydown($event)"
                    x-bind:disabled="mutationPending"
                >
                <span class="text-xs text-[var(--text-faint)]">into</span>
                <select class="s-input" style="max-width: 230px;" wire:model="quickAddDestination" x-bind:disabled="mutationPending">
                    @foreach($this->destinations as $dest)
                        <option value="{{ $dest['value'] }}" wire:key="dest-{{ $dest['value'] }}">{{ $dest['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <button type="button" class="s-btn s-btn-ghost s-btn-icon" title="Expand all" x-on:click="expandAll()">
                <flux:icon.arrows-pointing-out class="!size-4" />
            </button>
            <button type="button" class="s-btn s-btn-ghost s-btn-icon" title="Collapse all" x-on:click="collapseAll()">
                <flux:icon.arrows-pointing-in class="!size-4" />
            </button>
            <button type="button" class="s-btn s-btn-ghost s-btn-icon" title="New section" x-on:click="createInlineSection()">
                <flux:icon.folder-plus class="!size-4" />
            </button>
            <button type="button" class="s-btn s-btn-ghost s-btn-icon" title="Add free text item" x-on:click="createInlineTextLine()">
                <flux:icon.document-text class="!size-4" />
            </button>
            <button
                type="button"
                class="s-btn s-btn-ghost s-btn-sm"
                x-show="editable"
                x-on:click="$wire.recalculatePricing(false, false)"
                title="Recalculate pricing"
            >
                Recalculate
            </button>
            <button
                type="button"
                class="s-btn s-btn-ghost s-btn-icon"
                :class="priceLocked ? 'opacity-50 cursor-not-allowed' : ''"
                :disabled="priceLocked"
                :title="priceLocked ? 'Unlock price before setting a deal price' : 'Deal price'"
                x-on:click="!priceLocked && $dispatch('open-modal', 'deal-price')"
            >
                <flux:icon.currency-pound class="!size-4" x-bind:class="hasDealPrice ? '!text-red-600' : ''" />
            </button>
        @endif

        <div class="ml-auto flex items-center gap-2 lf-toolbar-actions">
            <span
                x-show="conflictCount > 0"
                x-cloak
                class="s-badge s-badge-red"
                title="Some lines were changed elsewhere"
            >
                <span class="s-badge-dot"></span>
                <span x-text="conflictCount + ' conflict' + (conflictCount === 1 ? '' : 's')"></span>
            </span>
            <span
                class="s-btn s-btn-ghost s-btn-icon inline-flex items-center justify-center"
                :class="{
                    'text-emerald-600': syncState === 'synced',
                    'text-amber-600': syncState === 'syncing',
                    'text-sky-600': syncState === 'cached',
                    'text-zinc-400': syncState === 'idle',
                }"
                :title="syncLabel + (queue.length ? ' (' + queue.length + ' pending)' : '')"
            >
                <span :class="{ 'animate-spin': syncState === 'syncing' }" class="inline-flex">
                    <flux:icon.arrow-path class="!size-4" />
                </span>
            </span>
        </div>
    </div>

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

    {{-- Alpine-owned table — Livewire must not morph this subtree --}}
    <x-dense::card class="[&_.s-card-body]:!p-0">
        <div class="overflow-x-auto" wire:ignore>
            <table class="s-table lf-table w-full text-sm select-none">
                <thead>
                    <tr>
                        <th class="text-left" style="min-width: 280px;">Product</th>
                        <th class="text-left">Type</th>
                        <th class="text-left">Status</th>
                        <th class="text-left lf-col-qty">Qty</th>
                        <th class="text-left lf-col-days">Days</th>
                        <th class="text-left lf-col-price">Price</th>
                        <th class="text-right lf-col-disc" style="text-align: right;">Disc %</th>
                        <th class="text-right lf-col-charge" style="text-align: right; white-space: nowrap;">
                            <span class="inline-flex items-center justify-end gap-1 w-full">
                                <span>Charge Total</span>
                                <button
                                    type="button"
                                    x-show="priceLocked && canManagePriceLock"
                                    x-cloak
                                    class="inline-flex items-center text-[var(--text-muted)] hover:text-[var(--brand-primary)]"
                                    title="Pricing is locked — click to unlock"
                                    @click="openLockPriceModal()"
                                >
                                    <flux:icon.lock-closed class="!size-3.5" />
                                </button>
                            </span>
                        </th>
                        <th class="text-right lf-col-actions"></th>
                    </tr>
                </thead>
                <tbody x-ref="tbody">
                    <template x-for="row in visibleRows" :key="row.id">
                        <tr
                            class="lf-row"
                            :class="{
                                'lf-row-group': row.item_type === 'group',
                                'lf-row-text': row.item_type === 'text',
                                'lf-row-added': row._justAdded,
                                'lf-row-removing': row._removing,
                                'lf-row-nest-target': nestDropGroupId === row.id,
                                'lf-row-dragging': dragId === row.id,
                                'lf-row-shortage': row.planning?.has_shortage || row.has_shortage,
                                'opacity-60': row.is_optional,
                            }"
                            :data-id="row.id"
                        >
                            <td>
                                <div class="flex items-center gap-1" :style="`padding-left:${(row.depth - 1) * 22}px`">
                                    <span
                                        x-show="editable"
                                        class="lf-handle"
                                        title="Drag to move / re-nest"
                                        @pointerdown="onHandleDown($event, row.id)"
                                    ><x-dense::icons.drag-handle size="4" aria-hidden="true" /></span>
                                    <span
                                        x-show="false"
                                        class="lf-handle"
                                        title="Drag to move / re-nest"
                                        @pointerdown="onHandleDown($event, row.id)"
                                    >☰</span>
                                    <button
                                        type="button"
                                        class="lf-caret"
                                        x-show="row.has_children"
                                        @click="toggleCollapse(row.id)"
                                        x-text="row.is_collapsed ? '+' : '−'"
                                    ></button>
                                    <span x-show="!row.has_children" class="lf-caret-spacer"></span>
                                    <template x-if="row.item_type === 'accessory'">
                                        <span class="text-[var(--text-muted)]">↳</span>
                                    </template>
                                    <template x-if="row.product_url">
                                        <a
                                            :href="row.product_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="lf-name lf-product-link"
                                            :class="{
                                                'italic': row.item_type === 'accessory',
                                            }"
                                            x-text="row.name"
                                        ></a>
                                    </template>
                                    <template x-if="!row.product_url">
                                        <span
                                            class="lf-name"
                                            :class="{
                                                'font-semibold': row.item_type === 'group',
                                                'italic': row.item_type === 'accessory',
                                            }"
                                            @dblclick="fieldsEditable && beginEdit(row.id, 'name', $event)"
                                            x-text="row.name"
                                        ></span>
                                    </template>
                                    <template x-if="row.is_optional">
                                        <span class="s-badge s-badge-zinc ml-1">Optional</span>
                                    </template>
                                </div>
                                <div
                                    x-show="row.item_type !== 'group' && (row.description || row.notes)"
                                    x-cloak
                                    class="mt-0.5 space-y-0.5 text-[11px] leading-snug text-[var(--text-muted)]"
                                    :style="`padding-left:${(row.depth - 1) * 22}px`"
                                >
                                    <div x-show="row.description" x-text="row.description"></div>
                                    <div x-show="row.notes" class="italic">
                                        <span class="not-italic text-[var(--text-faint)]">Warehouse:</span>
                                        <span x-text="row.notes"></span>
                                    </div>
                                </div>
                                <div
                                    x-show="row.planning"
                                    x-cloak
                                    class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] leading-snug text-[var(--text-muted)]"
                                    :style="`padding-left:${(row.depth - 1) * 22}px`"
                                >
                                    <span class="font-mono">Req <span x-text="fmtQty(row.planning?.requested_quantity)"></span></span>
                                    <template x-if="row.planning?.reservation_mode === 'legacy'">
                                        <span class="s-badge s-badge-muted text-[10px] uppercase tracking-wide">Legacy</span>
                                    </template>
                                    <template x-if="row.planning?.reservation_mode === 'explicit'">
                                        <span class="font-mono">Reserved <span x-text="fmtQty(row.planning?.reserved_quantity)"></span></span>
                                        <span class="s-badge s-badge-muted text-[10px] uppercase tracking-wide" x-text="'Reservation ' + row.planning.reservation_state"></span>
                                    </template>
                                    <span class="font-mono">Available <span x-text="fmtQty(row.planning?.available_quantity)"></span></span>
                                    <template x-if="row.planning?.has_shortage">
                                        <span class="s-badge s-badge-red text-[10px] uppercase tracking-wide">Shortage <span x-text="fmtQty(row.planning?.shortage_quantity)"></span></span>
                                    </template>
                                    <template x-if="row.planning?.external_supply_quantity > 0">
                                        <span class="font-mono">External <span x-text="fmtQty(row.planning?.external_supply_quantity)"></span></span>
                                    </template>
                                    <template x-if="row.planning?.allocated_quantity !== null">
                                        <span class="font-mono">Allocated <span x-text="fmtQty(row.planning?.allocated_quantity)"></span></span>
                                    </template>
                                </div>
                            </td>
                            <td>
                                <span x-show="row.item_type !== 'group'" class="s-chip" x-text="row.type_label || row.charge_period_label || '—'"></span>
                            </td>
                            <td>
                                <template x-if="row.item_type !== 'group' && row.status_label">
                                    <span class="s-badge" :class="statusClass(row.status_label)" x-text="row.status_label"></span>
                                </template>
                            </td>
                            <td class="text-left tabular-nums lf-col-qty">
                                <span x-show="row.item_type !== 'group'" class="lf-cell lf-cell-left" data-field="quantity" @click="fieldsEditable && beginEdit(row.id, 'quantity', $event)" x-text="fmtQty(row.quantity)"></span>
                            </td>
                            <td class="text-left tabular-nums lf-col-days">
                                <span x-show="row.item_type !== 'group'" class="lf-cell lf-cell-left" data-field="days" @click="fieldsEditable && beginEdit(row.id, 'days', $event)" x-text="row.days"></span>
                            </td>
                            <td class="text-left tabular-nums lf-col-price">
                                <span x-show="row.item_type !== 'group'" class="lf-cell lf-cell-left lf-cell-price" data-field="unit_price" @click="fieldsEditable && beginEdit(row.id, 'unit_price', $event)" x-text="row.unit_price_display"></span>
                            </td>
                            <td class="text-right tabular-nums lf-col-disc">
                                <span x-show="row.item_type !== 'group'" class="lf-cell inline-flex items-center justify-end gap-1" data-field="discount_percent" @click="fieldsEditable && beginEdit(row.id, 'discount_percent', $event)">
                                    <span x-text="(row.discount_percent ? row.discount_percent : 0) + '%'"></span>
                                    <span x-show="row.is_discount_manual" x-cloak class="s-badge s-badge-muted text-[10px] uppercase tracking-wide">Manual</span>
                                </span>
                            </td>
                            <td
                                class="text-right tabular-nums font-medium lf-charge-cell lf-col-charge"
                                style="white-space: nowrap;"
                                @mouseenter="showChargePopover($event, row)"
                                @mouseleave="hideChargePopoverSoon()"
                            >
                                <span x-show="row.item_type === 'group'" class="text-[var(--text-muted)]" x-text="groupSubtotalDisplay(row)"></span>
                                <span
                                    x-show="row.item_type !== 'group'"
                                    class="lf-charge-total"
                                    x-text="row.charge_total_display"
                                ></span>
                            </td>
                            <td class="text-right lf-col-actions">
                                <button type="button" class="s-btn-ghost s-btn-xs s-btn-icon" title="Actions" @click.stop="openRowMenu($event, row)">
                                    <svg viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!rows.length">
                        <td colspan="9" class="text-center text-[var(--text-muted)] py-4">
                            {{ $editable ? 'No line items yet. Use quick-add or add a section to start.' : 'This opportunity has no line items yet.' }}
                        </td>
                    </tr>
                </tbody>
                <tfoot x-show="rows.length">
                    <tr class="s-table-total-row" style="border-top: 2px solid var(--card-border);">
                        <td colspan="6"></td>
                        <td class="text-right font-semibold whitespace-nowrap">
                            <div>Charge total (ex-tax)</div>
                            <div
                                x-show="displayDealPriceSubline"
                                x-cloak
                                class="text-sm font-normal italic text-[var(--text-muted)] mt-0.5"
                                x-text="displayDealPriceSubline"
                            ></div>
                        </td>
                        <td class="text-right font-mono font-semibold text-lg tabular-nums whitespace-nowrap lf-footer-charge-total">
                            <span class="inline-flex items-center justify-end gap-1.5 w-full">
                                <button
                                    type="button"
                                    x-show="priceLocked && canManagePriceLock"
                                    x-cloak
                                    class="inline-flex items-center text-[var(--text-muted)] hover:text-[var(--brand-primary)]"
                                    title="Pricing is locked — click to unlock"
                                    @click="openLockPriceModal()"
                                >
                                    <flux:icon.lock-closed class="size-4!" />
                                </button>
                                <span x-text="displayGrandTotal"></span>
                            </span>
                        </td>
                        <td class="lf-col-actions"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @if($editable)
            <div class="lf-add-text-row flex flex-wrap items-stretch gap-2">
                <button type="button" class="s-btn s-btn-ghost lf-add-text-btn" x-on:click="createInlineTextLine()">
                    + Text Item
                </button>
                <button type="button" class="s-btn s-btn-ghost lf-add-text-btn" x-on:click="createInlineSection()">
                    + Section
                </button>
            </div>
        @endif
    </x-dense::card>

    <div x-ref="ghost" class="lf-ghost" x-show="dragId" x-cloak></div>

    {{-- Charge breakdown popover (teleported — avoids table overflow clipping) --}}
    <template x-teleport="body">
        <div
            x-show="chargePopover"
            x-cloak
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-1"
            class="lf-charge-popover lf-charge-popover--teleport"
            :style="chargePopover ? `position: fixed; z-index: 10050; top: ${chargePopover.top}px; left: ${chargePopover.left}px;` : ''"
            @mouseenter="keepChargePopover()"
            @mouseleave="chargePopover = null"
        >
            <template x-if="chargePopover">
                <div>
                    <div x-text="chargePopover.row.charge_breakdown?.days_line"></div>
                    <div>Rental charge amount: <span x-text="chargePopover.row.charge_breakdown?.rental_charge_display"></span></div>
                    <div>Surcharge amount: <span x-text="chargePopover.row.charge_breakdown?.surcharge_display"></span></div>
                </div>
            </template>
        </div>
    </template>

    {{-- Row actions menu (teleported — same s-dropdown pattern as the opportunities datatable) --}}
    <template x-teleport="body">
        <div
            x-show="openMenu !== null"
            x-cloak
            x-on:click.outside="onRowMenuOutsideClick($event)"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="s-dropdown"
            :style="`position: fixed; z-index: 9999; top: ${menuPos.top}px; right: ${menuPos.right}px; left: auto;`"
        >
            <template x-if="openMenu !== null">
                <div x-show="menuRow" x-cloak>
                    <template x-if="editable && menuRow.item_type === 'group'">
                        <button type="button" class="s-dropdown-item w-full text-left" @click="$dispatch('open-modal', { id: 'rename-section', itemId: menuRow.id, name: menuRow.name }); closeRowMenu()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                            Rename section
                        </button>
                    </template>
                    <template x-if="editable && menuRow.item_type === 'text'">
                        <button type="button" class="s-dropdown-item w-full text-left" @click="beginEdit(menuRow.id, 'name', $event); closeRowMenu()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                            Rename free text item
                        </button>
                    </template>
                    <template x-if="editable && menuRow.is_discount_manual">
                        <button type="button" class="s-dropdown-item w-full text-left" @click="resetItemDiscount(menuRow.id); closeRowMenu()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                            Reset to category discount
                        </button>
                    </template>
                    <template x-if="editable && menuRow.item_type !== 'group' && menuRow.item_type !== 'text'">
                        <button type="button" class="s-dropdown-item w-full text-left" @click="openEditLineModal(menuRow); closeRowMenu()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                            Edit line…
                        </button>
                    </template>
                    <template x-if="menuRow.availability_url">
                        <a :href="menuRow.availability_url" target="_blank" rel="noopener" class="s-dropdown-item" style="text-decoration: none;" @click="closeRowMenu()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                            View availability
                        </a>
                    </template>
                    <template x-if="editable && menuRow.item_type !== 'group' && menuRow.item_type !== 'text'">
                        <button type="button" class="s-dropdown-item w-full text-left" @click="toggleOptionalRow(menuRow); closeRowMenu()">
                            <svg x-show="menuRow.is_optional" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><polyline points="20 6 9 17 4 12"/></svg>
                            <svg x-show="!menuRow.is_optional" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><circle cx="12" cy="12" r="10"/></svg>
                            <span x-text="menuRow.is_optional ? 'Mark required' : 'Mark optional'"></span>
                        </button>
                    </template>
                    <template x-if="editable && menuRow.has_duplicates">
                        <button type="button" class="s-dropdown-item w-full text-left" @click="mergeDupes(menuRow); closeRowMenu()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></svg>
                            Merge duplicates
                        </button>
                    </template>
                    <template x-if="editable && groupMoveOptions(menuRow).length">
                        <div>
                            <div style="height: 1px; background: var(--card-border); margin: 4px 0;"></div>
                            <template x-for="g in groupMoveOptions(menuRow)" :key="g.id">
                                <button type="button" class="s-dropdown-item w-full text-left" @click="assignRowToGroup(menuRow, g.id); closeRowMenu()">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                    <span x-text="'Move to ' + g.name"></span>
                                </button>
                            </template>
                        </div>
                    </template>
                    <template x-if="editable && (!pricingFrozen || (menuRow.item_type === 'group' && !menuRow.has_children) || (menuRow.item_type === 'text' && !String(menuRow.name || '').trim()))">
                        <button
                            type="button"
                            class="s-dropdown-item w-full text-left"
                            :style="idsMatch(confirmDeleteId, menuRow.id) ? 'color: #fff; background: var(--red); width: 100%;' : 'color: var(--red); width: 100%;'"
                            @click.stop="handleRemoveMenuClick()"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="m21 8-2 13H5L3 8"/><path d="M7 8V6a4 4 0 0 1 4-4h2a4 4 0 0 1 4 4v2"/><path d="M1 8h22"/><path d="M10 12v6"/><path d="M14 12v6"/></svg>
                            <span x-text="idsMatch(confirmDeleteId, menuRow.id) ? 'Click again to confirm' : 'Remove'"></span>
                        </button>
                    </template>
                </div>
            </template>
        </div>
    </template>

    {{-- Product picker dropdown --}}
    <template x-teleport="body">
        <div x-ref="pickerDropdown" x-show="picker.open" x-cloak class="s-dropdown"
            style="position: absolute; z-index: 120; min-width: 300px; max-height: 340px; overflow-y: auto;"
            x-on:mousedown.prevent>
            <template x-for="(hit, i) in picker.results" :key="hit.id">
                <button type="button" class="s-dropdown-item flex items-center gap-2.5 w-full text-left lf-picker-hit"
                    :style="i === picker.highlight ? 'background: var(--s-subtle);' : ''"
                    x-on:mousedown.prevent="choosePickerHit(hit)"
                    x-on:mouseenter="picker.highlight = i">
                    <span class="lf-picker-product-thumb shrink-0 flex items-center justify-center overflow-hidden rounded border border-[var(--card-border)] bg-white"
                        style="width: 32px; height: 32px;">
                        <img x-show="hit.image_url" :src="hit.image_url" :alt="hit.name" class="size-full object-cover">
                        <span x-show="!hit.image_url" x-cloak class="font-bold text-[var(--text-muted)]" style="font-family: var(--font-display); font-size: 11px;" x-text="hit.initials || '?'"></span>
                    </span>
                    <span class="flex-1 min-w-0 truncate" x-text="hit.name"></span>
                    <span x-show="hit.is_combination" class="shrink-0 rounded border border-[var(--card-border)] px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-[var(--text-muted)]">Package</span>
                    <span class="font-mono text-xs text-[var(--text-faint)] shrink-0" x-text="hit.sku || ''"></span>
                </button>
            </template>
        </div>
    </template>

    {{-- Deal price modal --}}
    <x-signals.modal name="deal-price" title="Deal price" id="deal-price-modal">
        <div class="space-y-3">
            <p class="text-sm text-[var(--text-muted)]">
                Set a target net total. Flat headline mode locks line pricing; proportional mode distributes discounts across discountable lines.
            </p>
            <div>
                <label class="block text-sm font-medium mb-1">Mode</label>
                <select class="s-input w-full" x-model="dealPriceMode">
                    <option value="flat_headline">Flat headline override</option>
                    <option value="proportional">Proportional line distribution</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Deal total (ex-tax)</label>
                <input type="text" class="s-input w-full font-mono" x-model="dealPriceInput" placeholder="e.g. 750.00">
            </div>
            <x-slot:footer>
                <button type="button" class="s-btn s-btn-ghost" x-on:click="$dispatch('close-modal', 'deal-price')">Cancel</button>
                <button type="button" class="s-btn s-btn-ghost" x-show="hasDealPrice" x-on:click="clearDealPrice(); $dispatch('close-modal', 'deal-price')">Clear</button>
                <button type="button" class="s-btn s-btn-primary" x-on:click="applyDealPrice(); $dispatch('close-modal', 'deal-price')">Set deal price</button>
            </x-slot:footer>
        </div>
    </x-signals.modal>

    {{-- Create section modal --}}
    <x-signals.modal name="create-section" title="New section" id="create-section-modal">
        <div class="space-y-3">
            <div>
                <label class="block text-sm font-medium mb-1">Section name</label>
                <input type="text" class="s-input w-full" wire:model="newSectionName" wire:keydown.enter.prevent="createSection" placeholder="e.g. Front of House">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Nest under (optional)</label>
                <select class="s-input w-full" wire:model="newSectionParent">
                    @foreach($this->sectionOptions as $option)
                        <option value="{{ $option['value'] }}" wire:key="parent-opt-{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <x-slot:footer>
                <button type="button" class="s-btn s-btn-ghost" x-on:click="$dispatch('close-modal', 'create-section')">Cancel</button>
                <button type="button" class="s-btn s-btn-primary" wire:click="createSection">Create</button>
            </x-slot:footer>
        </div>
    </x-signals.modal>

    {{-- Rename section modal --}}
    <div x-data="{ open: false, itemId: null, name: '' }"
        x-on:open-modal.window="if ($event.detail?.id === 'rename-section') { open = true; itemId = $event.detail.itemId; name = $event.detail.name; }"
        x-on:line-items-mutation-done.window="if ($event.detail?.modalId === 'rename-section') open = false">
        <template x-teleport="body">
            <div class="s-modal-backdrop" x-show="open" x-cloak x-on:click.self="open = false">
                <div class="s-modal s-modal-md" x-trap.noscroll="open">
                    <div class="s-modal-header">
                        <span class="s-modal-title">Rename section</span>
                        <button class="s-modal-close" type="button" x-on:click="open = false">×</button>
                    </div>
                    <div class="s-modal-body">
                        <input type="text" class="s-input w-full" x-model="name">
                    </div>
                    <div class="s-modal-footer">
                        <button type="button" class="s-btn s-btn-ghost" x-on:click="open = false">Cancel</button>
                        <button type="button" class="s-btn s-btn-primary"
                            x-on:click="if (name.trim()) { $wire.renameItem(itemId, name.trim()); }">Save</button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- Edit line modal --}}
    <div x-data="{ open: false, line: null, unitPrice: '', discount: '', startsAt: '', endsAt: '', description: '', notes: '' }"
        x-on:open-modal.window="if ($event.detail?.id === 'edit-line') {
            open = true; line = $event.detail.line;
            unitPrice = line.unit_price_raw ?? '';
            discount = line.discount_percent ?? '';
            startsAt = line.starts_at ?? '';
            endsAt = line.ends_at ?? '';
            description = line.description ?? '';
            notes = line.notes ?? '';
        }"
        x-on:line-items-mutation-done.window="if ($event.detail?.modalId === 'edit-line') open = false">
        <template x-teleport="body">
            <div class="s-modal-backdrop" x-show="open" x-cloak x-on:click.self="open = false">
                <div class="s-modal s-modal-md" x-trap.noscroll="open">
                    <div class="s-modal-header">
                        <span class="s-modal-title">Edit line — <span x-text="line?.name"></span></span>
                        <button class="s-modal-close" type="button" x-on:click="open = false">×</button>
                    </div>
                    <div class="s-modal-body space-y-3">
                        <div>
                            <label class="block text-sm font-medium mb-1">Substitute product</label>
                            <input type="text" class="s-input w-full" placeholder="Search catalogue…" autocomplete="off"
                                x-ref="substituteInput"
                                x-on:input="onPickerInput($refs.substituteInput, $event.target.value, false, { mode: 'substitute', itemId: line?.id })"
                                x-on:keydown="onPickerKeydown($event, $refs.substituteInput, false)"
                                x-on:focus="onPickerInput($refs.substituteInput, $event.target.value, false, { mode: 'substitute', itemId: line?.id })"
                                x-on:blur="closePickerSoon()">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Unit price override</label>
                            <input type="text" class="s-input w-full font-mono" x-model="unitPrice">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Discount %</label>
                            <input type="number" min="0" max="100" step="0.01" class="s-input w-full font-mono" x-model="discount">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium mb-1">Starts</label>
                                <input type="date" class="s-input w-full" x-model="startsAt">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">Ends</label>
                                <input type="date" class="s-input w-full" x-model="endsAt">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Description</label>
                            <textarea class="s-input w-full" rows="2" x-model="description" placeholder="Customer-facing line description"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Warehouse notes</label>
                            <textarea class="s-input w-full" rows="2" x-model="notes" placeholder="Internal warehouse / prep notes"></textarea>
                        </div>
                    </div>
                    <div class="s-modal-footer">
                        <button type="button" class="s-btn s-btn-ghost" x-on:click="open = false">Cancel</button>
                        <button type="button" class="s-btn s-btn-primary"
                            x-on:click="line && $wire.saveLineEdits(
                                line.id,
                                unitPrice === '' ? null : unitPrice,
                                discount === '' ? null : discount,
                                startsAt || null,
                                endsAt || null,
                                description || null,
                                notes || null
                            )"
                        >Save</button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>

@push('scripts')
    <script
        src="https://unpkg.com/dexie@4.0.11/dist/dexie.min.js"
        integrity="sha384-cPcqy69aXDd/DVAhVjR7F1pRGPSPFhhPA108OQ8DcgTdZixJZc3W4gzurSW1WfgH"
        crossorigin="anonymous"
    ></script>
@endpush
