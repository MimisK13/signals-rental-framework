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
                    <template x-if="editable && menuRow.item_type !== 'group' && hasManyGroups()">
                        <button type="button" class="s-dropdown-item w-full text-left" @click="openGroupPanel(menuRow)">
                            <flux:icon.folder-plus class="!size-3.5" />
                            <span x-text="menuRow.parent_group_id ? 'Move to group' : 'Add to group'"></span>
                        </button>
                    </template>
                    <template x-if="editable && !hasManyGroups() && groupMoveOptions(menuRow).length">
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

