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

