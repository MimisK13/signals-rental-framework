<section
    class="relative w-full"
    x-data="{ quickCreateOpen: new URLSearchParams(window.location.search).get('quickAdd') === '1', quickEditLoading: false }"
    x-on:close-product-group-quick-create.window="quickCreateOpen = false; quickEditLoading = false"
    x-on:open-product-group-quick-create.window="quickCreateOpen = true; quickEditLoading = false"
    x-on:open-product-group-quick-edit.window="quickCreateOpen = true; quickEditLoading = true"
    x-on:product-group-quick-edit-loaded.window="quickCreateOpen = true; quickEditLoading = false"
>
    <x-dense::page-header title="Product Groups">
        <x-slot:meta>
            <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">Catalogue</span>
        </x-slot:meta>
    </x-dense::page-header>

    <div class="flex min-w-0 flex-1 items-stretch max-md:flex-col" :class="{ 'pr-[360px] max-md:pr-0': quickCreateOpen }">
        <div class="min-w-0 flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
            <livewire:components.data-table
                :columns="$columns"
                :model="\App\Domain\Products\Models\ProductGroup::class"
                :searchable="['name']"
                :with="['parent']"
                :with-counts="['products']"
                :refresh-events="['group-deleted', 'group-created', 'group-updated']"
                default-sort="name"
                empty-message="No product groups found."
                actions-view="products::product-groups.partials.row-actions"
                bulk-actions-view="products::product-groups.partials.bulk-actions"
                toolbar-view="products::product-groups.partials.toolbar"
                entity-type="product_groups"
            />
        </div>

        <x-dense::side-panel
            x-show="quickCreateOpen"
            x-cloak
            mode="viewport"
            ::class="{ 'dense-side-panel--loading': quickEditLoading }"
            :title="$quickGroupId ? 'Edit Product Group' : 'New Product Group'"
            close="$wire.closeQuickCreate(); quickCreateOpen = false"
        >
            <div
                x-show="quickEditLoading"
                x-cloak
                class="dense-side-panel-loading"
                role="status"
                aria-live="polite"
                aria-label="Loading product group"
            >
                <span class="dense-loader dense-loader--pulse" aria-hidden="true"></span>
            </div>

            <form
                id="quick-product-group-form"
                wire:submit="saveQuickGroup"
                class="space-y-4"
                x-show="!quickEditLoading"
            >
                {{--
                    TODO: another implementation / snippet it.
                    Preserve this Group Image implementation for future reuse.
                --}}
                {{--
                <div>
                    <x-dense::field-label for="quick-group-photo">Group Image</x-dense::field-label>
                    <div class="flex items-start gap-3">
                        <div class="relative flex size-16 shrink-0 items-center justify-center overflow-hidden rounded border" style="border-color: var(--erp-color-border); background: var(--dense-surface-subtle);">
                            <input
                                id="quick-group-photo"
                                type="file"
                                wire:model="quickGroupPhoto"
                                accept="image/*"
                                class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0"
                                aria-label="Choose Product Group Image"
                            />
                            @if($quickGroupPhoto)
                                <img src="{{ $quickGroupPhoto->temporaryUrl() }}" alt="" class="size-full object-cover" />
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6" aria-hidden="true">
                                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z" />
                                </svg>
                            @endif
                        </div>
                    </div>
                    <x-dense::field-error :message="$errors->first('quickGroupPhoto')" />
                </div>
                --}}

                @if($this->groupedCustomFields->isNotEmpty())
                    <div x-data="denseTabs({ count: 2 })" class="dense-tabs dense-tabs-subheader">
                        <div class="dense-tabs-list" role="tablist" aria-label="Product group sections">
                            <button
                                type="button"
                                id="quick-group-details-tab"
                                class="dense-tabs-trigger"
                                role="tab"
                                :aria-selected="active === 0"
                                :tabindex="active === 0 ? 0 : -1"
                                aria-controls="quick-group-details-panel"
                                x-on:click="activate(0)"
                                x-on:keydown="onKeydown($event)"
                            >
                                Details
                            </button>
                            <button
                                type="button"
                                id="quick-group-custom-fields-tab"
                                class="dense-tabs-trigger"
                                role="tab"
                                :aria-selected="active === 1"
                                :tabindex="active === 1 ? 0 : -1"
                                aria-controls="quick-group-custom-fields-panel"
                                x-on:click="activate(1)"
                                x-on:keydown="onKeydown($event)"
                            >
                                Custom Fields
                            </button>
                        </div>

                        <div
                            id="quick-group-details-panel"
                            class="dense-tabs-panel space-y-4"
                            role="tabpanel"
                            aria-labelledby="quick-group-details-tab"
                            x-cloak
                            x-show="active === 0"
                        >
                @endif

                <x-dense::field
                    label="Name"
                    for="quick-group-name"
                    required
                    :error="$errors->first('quickGroupName')"
                    error-id="quick-group-name-error"
                >
                    <x-dense::input
                        id="quick-group-name"
                        wire:model="quickGroupName"
                        required
                        :invalid="$errors->has('quickGroupName')"
                        :aria-describedby="$errors->has('quickGroupName') ? 'quick-group-name-error' : null"
                        autofocus
                        autocomplete="off"
                    />
                </x-dense::field>

                <x-dense::field
                    label="Description"
                    for="quick-group-description"
                    :error="$errors->first('quickGroupDescription')"
                    error-id="quick-group-description-error"
                >
                    <x-dense::textarea
                        id="quick-group-description"
                        wire:model="quickGroupDescription"
                        :invalid="$errors->has('quickGroupDescription')"
                        :aria-describedby="$errors->has('quickGroupDescription') ? 'quick-group-description-error' : null"
                        rows="3"
                    />
                </x-dense::field>

                <x-dense::field
                    label="Parent Group"
                    for="quick-parent-group"
                    :error="$errors->first('quickParentId')"
                    error-id="quick-parent-group-error"
                >
                    <x-dense::combobox
                        id="quick-parent-group"
                        model="quickParentId"
                        :selected="$quickParentId"
                        :options="collect($parentOptions)->map(fn ($name, $id) => ['id' => (int) $id, 'name' => $name])->values()->all()"
                        :limit="5"
                        :invalid="$errors->has('quickParentId')"
                        :aria-describedby="$errors->has('quickParentId') ? 'quick-parent-group-error' : null"
                        placeholder="Select parent group"
                    />
                </x-dense::field>

                <x-dense::field
                    label="Group Image"
                    for="quick-group-dropzone"
                    :error="$errors->first('quickGroupPhoto')"
                >
                    <x-dense::dropzone
                        id="quick-group-dropzone"
                        model="quickGroupDropzone"
                        clear-method="removeQuickGroupImage"
                        reset-method="clearQuickGroupDropzone"
                        :preview="$quickGroupDropzone ? $quickGroupDropzone->temporaryUrl() : $quickGroupImageUrl"
                        accept="image/*"
                        placeholder="Drag an image here or click to browse."
                    />
                </x-dense::field>

                @if($this->groupedCustomFields->isNotEmpty())
                        </div>
                        <div
                            id="quick-group-custom-fields-panel"
                            class="dense-tabs-panel"
                            role="tabpanel"
                            aria-labelledby="quick-group-custom-fields-tab"
                            x-cloak
                            x-show="active === 1"
                        >
                    <x-signals.custom-fields-editor
                        :groupedCustomFields="$groupedCustomFields"
                        prefix="quickCustomFieldValues"
                        :nativeRequired="false"
                        dense
                    />
                        </div>
                    </div>
                @endif

            </form>

            <x-slot:footer>
                <div
                    class="dense-side-panel-footer-actions"
                    x-show="!quickEditLoading"
                >
                <button
                    type="button"
                    class="s-btn s-btn-sm s-btn-ghost"
                    x-on:click="$wire.closeQuickCreate(); quickCreateOpen = false"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    form="quick-product-group-form"
                    class="s-btn s-btn-sm s-btn-accent"
                    wire:loading.attr="disabled"
                    wire:target="saveQuickGroup"
                >
                    <span wire:loading.remove wire:target="saveQuickGroup">{{ $quickGroupId ? 'Save Changes' : 'Create Group' }}</span>
                    <span wire:loading wire:target="saveQuickGroup">{{ $quickGroupId ? 'Saving…' : 'Creating…' }}</span>
                </button>
                </div>
            </x-slot:footer>
        </x-dense::side-panel>
    </div>
</section>
