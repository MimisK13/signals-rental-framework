<section class="w-full">
    <x-admin.layout title="Stores" description="Manage your store locations.">
        <x-slot:actions>
            <flux:button variant="primary" wire:click="openCreateModal">Add Store</flux:button>
        </x-slot:actions>

        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>City</th>
                        <th>Country</th>
                        <th>Default</th>
                        <th class="w-[120px]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stores as $store)
                        <tr wire:key="store-{{ $store->id }}">
                            <td class="font-medium">{{ $store->name }}</td>
                            <td>{{ $store->city }}</td>
                            <td>{{ $store->country_code }}</td>
                            <td>
                                @if($store->is_default)
                                    <span class="s-badge s-badge-green">Default</span>
                                @else
                                    <span class="s-badge">Standard</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @include('stores::admin.partials.row-actions', ['store' => $store])
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-[var(--text-muted)]">No stores configured.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Create / Edit Modal --}}
        <div
            x-data="{
                open: false,
                focusAfter: null,
                openModal() {
                    this.focusAfter = document.activeElement
                    this.open = true
                },
                closeModal() {
                    this.open = false
                    this.$nextTick(() => this.focusAfter?.focus())
                },
            }"
            x-on:open-store-modal.window="openModal()"
            x-on:close-store-modal.window="closeModal()"
            x-id="['store-modal-title']"
        >
            <template x-teleport="body">
                <div class="s-modal-backdrop"
                     x-show="open"
                     x-cloak
                     x-transition.opacity
                     x-on:click.self="closeModal()"
                     x-on:keydown.escape.prevent.stop.window="closeModal()"
                     role="dialog"
                     aria-modal="true"
                     :aria-labelledby="$id('store-modal-title')">
                    <div class="s-modal-md s-modal" x-trap.noscroll.inert="open">
                        <div class="s-modal-header">
                            <span class="s-modal-title" :id="$id('store-modal-title')">
                                {{ $editingStoreId ? 'Edit Store' : 'Add Store' }}
                            </span>
                            <button class="s-modal-close" type="button" x-on:click="closeModal()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>
                        <div class="s-modal-body">
                            <form wire:submit="saveStore" class="space-y-4">
                                <flux:input wire:model="storeName" label="Store Name" required />

                                <flux:input wire:model="storeStreet" label="Street Address" />

                                <div class="grid grid-cols-2 gap-4">
                                    <flux:input wire:model="storeCity" label="City" />
                                    <flux:input wire:model="storeCounty" label="County / State" />
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <flux:input wire:model="storePostcode" label="Postcode / ZIP" />
                                    <flux:select wire:model="storeCountryCode" label="Country" required>
                                        <option value="">Select country...</option>
                                        @foreach($countryOptions as $code => $countryName)
                                            <option value="{{ $code }}">{{ $countryName }}</option>
                                        @endforeach
                                    </flux:select>
                                </div>

                                <div class="flex justify-end gap-3 pt-2">
                                    <flux:button type="button" x-on:click="closeModal()">Cancel</flux:button>
                                    <flux:button variant="primary" type="submit">
                                        {{ $editingStoreId ? 'Update Store' : 'Add Store' }}
                                    </flux:button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </x-admin.layout>
</section>
