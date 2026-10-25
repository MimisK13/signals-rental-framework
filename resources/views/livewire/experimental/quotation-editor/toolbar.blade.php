<div class="sticky top-0 z-30 mb-3 flex flex-wrap items-center gap-2 bg-[var(--content-bg)]">
    @if($editable)
        <div class="flex items-center gap-2"> {{--- lf-quick-add-bar ---}}
            <div class="w-[30rem] max-w-full min-w-0 flex-1">
                <div class="relative flex-1 min-w-0">
                    <x-dense::input
                        id="quick-add-product"
                        type="text"
                        class="h-8 w-full"
                        aria-label="Quick add product"
                        placeholder='Quick add - "6 spider", or a SKU, then Enter'
                        autocomplete="off"
                        x-model="quickAddQuery"
                        x-ref="quickAddInput"
                        x-on:input="onPickerInput($refs.quickAddInput, quickAddQuery, true)"
                        x-on:keydown="onQuickAddInputKeydown($event, $refs.quickAddInput)"
                        x-on:focus="onPickerInput($refs.quickAddInput, quickAddQuery, true)"
                        x-on:blur="closePickerSoon()"
                        x-bind:disabled="mutationPending"
                    />

                    <span class="text-xs text-[var(--text-faint)] font-mono" x-show="quickAddQtyHint" x-text="quickAddQtyHint"></span>
                </div>
            </div>
            <div class="w-20 shrink-0">
                <x-dense::input
                    id="quick-add-qty"
                    type="number"
                    min="1"
                    step="1"
                    placeholder="1"
                    class="h-8 w-full text-center font-mono"
                    aria-label="Quantity"
                    x-ref="quickAddQty"
                    x-model="quickAddQty"
                    x-on:keydown="onQuickAddQtyKeydown($event)"
                    x-bind:disabled="mutationPending"
                />
            </div>
            <div class="shrink-0">
                <x-dense::select id="quick-add-destination" aria-label="Group" class="h-8 !py-0 !leading-8" wire:model="quickAddDestination" x-bind:disabled="mutationPending">
                    @foreach($this->destinations as $dest)
                        <option value="{{ $dest['value'] }}" wire:key="dest-{{ $dest['value'] }}">{{ $dest['value'] === '' ? 'Auto group' : $dest['label'] }}</option>
                    @endforeach
                </x-dense::select>
            </div>
        </div>

    @endif

    <div class="ml-auto flex items-end gap-2 lf-toolbar-actions">
        @if($editable)
            <button
                type="button"
                class="s-btn s-btn-ghost s-btn-icon"
                title="Expand all"
                x-on:click="expandAll()"
                x-bind:disabled="sectionStatePending"
                x-bind:aria-busy="sectionStatePending"
                :class="{ 'cursor-wait opacity-60': sectionStatePending }"
            >
                <flux:icon.arrows-pointing-out x-show="!sectionStatePending" class="!size-4" />
                <flux:icon.arrow-path x-show="sectionStatePending" x-cloak class="!size-4 animate-spin" />
            </button>
            <button
                type="button"
                class="s-btn s-btn-ghost s-btn-icon"
                title="Collapse all"
                x-on:click="collapseAll()"
                x-bind:disabled="sectionStatePending"
                x-bind:aria-busy="sectionStatePending"
                :class="{ 'cursor-wait opacity-60': sectionStatePending }"
            >
                <flux:icon.arrows-pointing-in x-show="!sectionStatePending" class="!size-4" />
                <flux:icon.arrow-path x-show="sectionStatePending" x-cloak class="!size-4 animate-spin" />
            </button>
            <button
                type="button"
                class="s-btn s-btn-ghost s-btn-icon"
                title="New section"
                x-on:click="createInlineSection()"
                x-bind:disabled="structuralMutationPending"
                x-bind:aria-busy="structuralMutationPending"
                :class="{ 'cursor-wait opacity-60': structuralMutationPending }"
            >
                <flux:icon.folder-plus x-show="!structuralMutationPending" class="!size-4" />
                <flux:icon.arrow-path x-show="structuralMutationPending" x-cloak class="!size-4 animate-spin" />
            </button>
            <button
                type="button"
                class="s-btn s-btn-ghost s-btn-icon"
                title="Add free text item"
                x-on:click="createInlineTextLine()"
                x-bind:disabled="structuralMutationPending"
                x-bind:aria-busy="structuralMutationPending"
                :class="{ 'cursor-wait opacity-60': structuralMutationPending }"
            >
                <flux:icon.document-text x-show="!structuralMutationPending" class="!size-4" />
                <flux:icon.arrow-path x-show="structuralMutationPending" x-cloak class="!size-4 animate-spin" />
            </button>
            <button
                type="button"
                class="s-btn s-btn-ghost s-btn-sm inline-flex items-center gap-1.5"
                x-on:click="recalculatePricing()"
                x-bind:disabled="pricingRecalculating || mutationPending"
                :class="{ 'cursor-wait opacity-60': pricingRecalculating, 'cursor-not-allowed opacity-60': mutationPending }"
                :title="pricingRecalculating ? 'Recalculating pricing...' : 'Recalculate pricing'"
            >
                <flux:icon.arrow-path x-show="pricingRecalculating" x-cloak class="!size-3.5 animate-spin" />
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
