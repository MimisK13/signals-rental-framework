{{-- Alpine-owned table — Livewire must not morph this subtree. --}}
<x-dense::table-card class="relative !shadow-none [&_.card-footer]:!border-t-0 [&_.card-footer]:!px-3 [&_.card-footer]:!py-2">
    <div
        x-show="pricingRecalculating"
        x-cloak
        class="absolute inset-0 z-20 flex items-center justify-center bg-[var(--card-bg)]"
        role="status"
        aria-live="polite"
    >
        <div class="inline-flex items-center gap-2 text-xs font-medium text-[var(--text-muted)]">
            <flux:icon.arrow-path class="!size-4 animate-spin" />
            <span>Recalculating pricing...</span>
        </div>
    </div>

    <div class="overflow-x-auto" wire:ignore>
        <table class="s-table s-table-compact w-full select-none [&_thead_th]:!py-[10px] [&_thead_th]:!text-[9px] [&_thead_th]:!font-semibold">
            <thead>
                <tr>
                    <th class="lf-col-drag"></th>
                    <th x-show="hasSectionChildren" x-cloak class="w-9 min-w-9"></th>
                    <th class="text-left min-w-[280px]">Product</th>
                    <th class="text-left">Type</th>
                    <th class="text-left">Status</th>
                    <th class="text-left lf-col-qty">Qty</th>
                    <th class="text-left lf-col-days">Days</th>
                    <th class="text-left lf-col-price">Price</th>
                    <th class="text-right lf-col-disc">Disc %</th>
                    <th class="text-right lf-col-charge whitespace-nowrap">
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
                    <th class="w-15 min-w-15 max-w-15 text-center align-middle lf-col-actions"></th>
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
                            'lf-row-shortage': row.planning?.has_shortage || row.has_shortage,
                            'opacity-60': row.is_optional,
                        }"
                        :data-id="row.id"
                    >
                        <td class="lf-col-drag w-9 min-w-9 text-center align-middle">
                            <span
                                x-show="editable && row.depth <= 1"
                                x-cloak
                                class="lf-handle lf-drag-cell-handle inline-flex size-6 items-center justify-center p-0"
                                @pointerdown="onHandleDown($event, row.id)" title="Drag to move / re-nest"
                            >
                                <svg
                                    viewBox="0 0 16 16"
                                    class="size-4 text-[var(--text-muted)] transition-colors hover:text-[var(--text-primary)]"
                                    fill="none"
                                    aria-hidden="true"
                                >
                                    <circle cx="5" cy="3" r="1.25" fill="currentColor" />
                                    <circle cx="11" cy="3" r="1.25" fill="currentColor" />
                                    <circle cx="5" cy="8" r="1.25" fill="currentColor" />
                                    <circle cx="11" cy="8" r="1.25" fill="currentColor" />
                                    <circle cx="5" cy="13" r="1.25" fill="currentColor" />
                                    <circle cx="11" cy="13" r="1.25" fill="currentColor" />
                                </svg>
                            </span>
                        </td>
                        <td x-show="hasSectionChildren" x-cloak class="w-9 min-w-9 text-center align-middle">
                            <button
                                type="button"
                                x-show="row.item_type === 'group' && row.has_children"
                                class="s-btn-ghost s-btn-xs s-btn-icon inline-flex items-center justify-center"
                                :title="row.is_collapsed ? 'Expand section' : 'Collapse section'"
                                x-bind:disabled="sectionStatePending"
                                x-bind:aria-busy="sectionStatePending"
                                :class="{ 'cursor-wait opacity-60': sectionStatePending }"
                                @click="toggleCollapse(row.id)"
                            >
                                <span x-show="!sectionStatePending && row.is_collapsed">
                                    <x-dense::icons.folder-plus size="4" />
                                </span>
                                <span x-show="!sectionStatePending && !row.is_collapsed">
                                    <x-dense::icons.folder-minus size="4" />
                                </span>
                                <flux:icon.arrow-path x-show="sectionStatePending" x-cloak class="!size-3.5 animate-spin" />
                            </button>
                            <span
                                x-show="editable && row.depth > 1 && row.item_type !== 'group'"
                                x-cloak
                                class="lf-handle lf-drag-cell-handle inline-flex size-6 items-center justify-center p-0"
                                @pointerdown="onHandleDown($event, row.id)"
                                title="Drag to move / re-nest"
                            >
                                <svg
                                    viewBox="0 0 16 16"
                                    class="size-4 text-[var(--text-muted)] transition-colors hover:text-[var(--text-primary)]"
                                    fill="none"
                                    aria-hidden="true"
                                >
                                    <circle cx="5" cy="3" r="1.25" fill="currentColor" />
                                    <circle cx="11" cy="3" r="1.25" fill="currentColor" />
                                    <circle cx="5" cy="8" r="1.25" fill="currentColor" />
                                    <circle cx="11" cy="8" r="1.25" fill="currentColor" />
                                    <circle cx="5" cy="13" r="1.25" fill="currentColor" />
                                    <circle cx="11" cy="13" r="1.25" fill="currentColor" />
                                </svg>
                            </span>
                        </td>
                        <td>
                            <div class="flex items-center gap-1">
                                <template x-if="row.item_type === 'accessory'">
                                    <span class="text-[var(--text-muted)]">↳</span>
                                </template>
                                <template x-if="row.product_url">
                                    <a
                                        :href="row.product_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="lf-name lf-product-link"
                                        :class="{ 'italic': row.item_type === 'accessory' }"
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
                                x-show="row.product_id && row.planning"
                                x-cloak
                                class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0 text-xs leading-tight text-[var(--text-muted)]"
                            >
                                <span class="inline-flex items-center gap-1">
                                    <span class="font-semibold tracking-wide">REQ</span>
                                    <span class="tabular-nums" x-text="fmtQty(row.planning?.requested_quantity)"></span>
                                </span>
                                <template x-if="row.planning?.reservation_mode === 'explicit'">
                                    <span class="inline-flex items-center gap-1">
                                        <span class="font-semibold tracking-wide">RES</span>
                                        <span class="tabular-nums" x-text="fmtQty(row.planning?.reserved_quantity)"></span>
                                    </span>
                                    <span class="uppercase tracking-wide" x-text="row.planning.reservation_state"></span>
                                </template>
                                <span class="inline-flex items-center gap-1">
                                    <span class="font-semibold tracking-wide">AVAIL</span>
                                    <span class="tabular-nums" x-text="fmtQty(row.planning?.available_quantity)"></span>
                                </span>
                                <template x-if="row.planning?.has_shortage">
                                    <span class="font-semibold uppercase tracking-wide text-[var(--danger)]">SHORTAGE <span class="tabular-nums" x-text="fmtQty(row.planning?.shortage_quantity)"></span></span>
                                </template>
                                <template x-if="row.planning?.external_supply_quantity > 0">
                                    <span class="inline-flex items-center gap-1">
                                        <span class="font-semibold tracking-wide">EXT</span>
                                        <span class="tabular-nums" x-text="fmtQty(row.planning?.external_supply_quantity)"></span>
                                    </span>
                                </template>
                                <template x-if="row.planning?.allocated_quantity !== null">
                                    <span class="inline-flex items-center gap-1">
                                        <span class="font-semibold tracking-wide">ALLOC</span>
                                        <span class="tabular-nums" x-text="fmtQty(row.planning?.allocated_quantity)"></span>
                                    </span>
                                </template>
                            </div>
                        </td>
                        <td>
                            <template x-if="row.item_type === 'group'">
                                <span class="s-chip">SECTION</span>
                            </template>
                            <template x-if="row.item_type !== 'group'">
                                <span class="s-chip" x-text="row.type_label || row.charge_period_label || '—'"></span>
                            </template>
                        </td>
                        <td>
                            <template x-if="row.item_type !== 'group' && row.item_type !== 'text' && (row.status_label || row.has_shortage || row.planning?.has_shortage || Number(row.planning?.available_quantity) > 0)">
                                <span
                                    class="s-badge"
                                    :class="statusClass(row.status_label || (row.has_shortage || row.planning?.has_shortage ? 'Shortage' : 'Available'))"
                                    x-text="row.status_label || (row.has_shortage || row.planning?.has_shortage ? 'Shortage' : 'Available')"
                                ></span>
                            </template>
                        </td>
                        <td class="text-left tabular-nums tracking-wide lf-col-qty">
                            <span x-show="row.item_type !== 'group'" class="lf-cell lf-cell-left" data-field="quantity" @click="fieldsEditable && beginEdit(row.id, 'quantity', $event)" x-text="fmtQty(row.quantity)"></span>
                        </td>
                        <td class="text-left tabular-nums tracking-wide lf-col-days">
                            <span x-show="row.item_type !== 'group'" class="lf-cell lf-cell-left" data-field="days" @click="fieldsEditable && beginEdit(row.id, 'days', $event)" x-text="row.days"></span>
                        </td>
                        <td class="text-left tabular-nums tracking-wide lf-col-price">
                            <span x-show="row.item_type !== 'group'" class="lf-cell lf-cell-left lf-cell-price" data-field="unit_price" @click="fieldsEditable && beginEdit(row.id, 'unit_price', $event)" x-text="row.unit_price_display"></span>
                        </td>
                        <td class="text-right tabular-nums tracking-wide lf-col-disc">
                            <span x-show="row.item_type !== 'group'" class="lf-cell inline-flex items-center justify-end gap-1" data-field="discount_percent" @click="fieldsEditable && beginEdit(row.id, 'discount_percent', $event)">
                                <span x-text="(row.discount_percent ? row.discount_percent : 0) + '%'" ></span>
                                <span x-show="row.is_discount_manual" x-cloak class="s-badge s-badge-muted text-[10px] uppercase tracking-wide">Manual</span>
                            </span>
                        </td>
                        <td
                            class="text-right tabular-nums tracking-wide font-medium lf-charge-cell lf-col-charge whitespace-nowrap"
                            @mouseenter="showChargePopover($event, row)"
                            @mouseleave="hideChargePopoverSoon()"
                        >
                            <span x-show="row.item_type === 'group'" class="text-[var(--text-muted)]" x-text="groupSubtotalDisplay(row)"></span>
                            <span x-show="row.item_type !== 'group'" class="lf-charge-total" x-text="row.charge_total_display"></span>
                        </td>
                        <td class="w-15 min-w-15 max-w-15 text-center align-middle lf-col-actions">
                            <button type="button" class="s-btn-ghost s-btn-xs s-btn-icon" title="Actions" @click.stop="openRowMenu($event, row)">
                                <svg viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
                            </button>
                        </td>
                    </tr>
                </template>
                <tr x-show="!rows.length" x-cloak>
                    <td :colspan="hasSectionChildren ? 11 : 10" class="py-4 text-center align-middle text-[var(--text-muted)]">
                        <div class="mx-auto flex min-h-16 w-full items-center justify-center text-center">
                            {{ $editable ? 'No line items yet. Use quick-add or add a section to start.' : 'This opportunity has no line items yet.' }}
                        </div>
                    </td>
                </tr>
            </tbody>
            <tfoot x-show="rows.length" x-cloak>
                <tr class="h-16">
                    <td :colspan="hasSectionChildren ? 8 : 7"></td>
                    <td class="py-4 text-right font-semibold whitespace-nowrap">
                        <div>Charge total (ex-tax)</div>
                        <div x-show="displayDealPriceSubline" x-cloak class="text-sm font-normal italic text-[var(--text-muted)] mt-0.5" x-text="displayDealPriceSubline"></div>
                    </td>
                    <td class="py-4 text-right !font-mono text-lg font-semibold tabular-nums tracking-wide whitespace-nowrap">
                        <span class="inline-flex items-center justify-end gap-1.5 w-full">
                            <button type="button" x-show="priceLocked && canManagePriceLock" x-cloak class="inline-flex items-center text-[var(--text-muted)] hover:text-[var(--brand-primary)]" title="Pricing is locked — click to unlock" @click="openLockPriceModal()">
                                <flux:icon.lock-closed class="size-4!" />
                            </button>
                            <span x-text="displayGrandTotal"></span>
                        </span>
                    </td>
                    <td class="w-15 min-w-15 max-w-15 text-center align-middle lf-col-actions"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</x-dense::table-card>

@if($editable)
    <div class="flex w-full flex-wrap items-center gap-1 py-2">
        <button type="button" class="s-btn s-btn-ghost s-btn-xs lf-add-text-btn" x-on:click="createInlineTextLine()">+ Text Item</button>
        <button type="button" class="s-btn s-btn-ghost s-btn-xs lf-add-text-btn" x-on:click="createInlineSection()">+ Section</button>
    </div>
@endif
