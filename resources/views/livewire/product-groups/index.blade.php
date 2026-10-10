<?php

use App\Actions\Products\CreateProductGroup;
use App\Data\Products\CreateProductGroupData;
use App\Data\Products\UpdateProductGroupData;
use App\Actions\Products\UpdateProductGroup;
use App\Livewire\Concerns\LoadsCustomFieldValues;
use App\Livewire\Concerns\ReKeysCustomFieldErrors;
use App\Models\CustomField;
use App\Models\ProductGroup;
use App\Services\FileService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] #[Title('Product Groups')] class extends Component
{
    use WithFileUploads;
    use LoadsCustomFieldValues;
    use ReKeysCustomFieldErrors;

    public ?int $quickGroupId = null;

    public string $quickGroupName = '';

    public string $quickGroupDescription = '';

    public ?int $quickParentId = null;

    public mixed $quickGroupPhoto = null;

    public mixed $quickGroupDropzone = null;

    public ?string $quickGroupImageUrl = null;

    /** @var array<string, mixed> */
    public array $quickCustomFieldValues = [];

    /** @var array<int|string, string>|null */
    private ?array $parentOptionsCache = null;

    public function saveQuickGroup(): void
    {
        $this->validate([
            'quickGroupName' => ['required', 'string', 'max:255'],
            'quickGroupDescription' => ['nullable', 'string'],
            'quickParentId' => ['nullable', 'integer', 'exists:product_groups,id'],
            'quickGroupDropzone' => ['nullable', 'image', 'max:2048', 'mimes:jpeg,jpg,png,webp,gif'],
        ]);

        try {
            $wasEditing = $this->quickGroupId !== null;

            if ($wasEditing) {
                $group = ProductGroup::findOrFail($this->quickGroupId);
                $result = (new UpdateProductGroup)($group, UpdateProductGroupData::from([
                    'name' => $this->quickGroupName,
                    'description' => $this->quickGroupDescription ?: null,
                    'parent_id' => $this->quickParentId,
                    'custom_fields' => $this->quickCustomFieldValues,
                ]));
            } else {
                $result = (new CreateProductGroup)(CreateProductGroupData::from([
                    'name' => $this->quickGroupName,
                    'description' => $this->quickGroupDescription ?: null,
                    'parent_id' => $this->quickParentId,
                    'custom_fields' => $this->quickCustomFieldValues,
                ]));
            }

            if ($this->quickGroupDropzone) {
                $group = ProductGroup::findOrFail($result->id);
                $upload = app(FileService::class)->uploadIcon($this->quickGroupDropzone, $group);
                $group->update([
                    'icon_url' => $upload['icon_url'],
                    'icon_thumb_url' => $upload['icon_thumb_url'],
                ]);
            }
        } catch (ValidationException $exception) {
            $customFieldNames = CustomField::query()
                ->forModule('ProductGroup')
                ->pluck('name')
                ->all();
            $this->reKeyCustomFieldErrors($exception, $customFieldNames, 'quickCustomFieldValues');
        } catch (\Throwable $exception) {
            report($exception);
            $this->addError('quickGroupDropzone', 'Failed to upload image. You can add one from the edit screen.');

            return;
        }

        $this->dispatch('close-product-group-quick-create');
        $this->dispatch($wasEditing ? 'group-updated' : 'group-created');
        $this->dispatch('toast', type: 'success', message: __($wasEditing ? 'Product group updated.' : 'Product group created.'));
        $this->resetQuickCreate();
    }

    public function resetQuickCreate(bool $resetInteractiveState = true): void
    {
        $this->resetValidation();
        $this->quickGroupId = null;
        $this->quickGroupName = '';
        $this->quickGroupDescription = '';
        $this->quickParentId = null;
        $this->quickGroupPhoto = null;
        $this->quickGroupDropzone = null;
        $this->quickGroupImageUrl = null;
        $this->quickCustomFieldValues = [];
        if ($resetInteractiveState) {
            $this->dispatch('dense-combobox-reset');
            $this->dispatch('dense-dropzone-reset');
        }
    }

    public function editQuickGroup(int $groupId): void
    {
        $group = ProductGroup::findOrFail($groupId);

        $this->clearQuickGroupDropzone();
        $this->resetQuickCreate(false);
        $this->quickGroupId = $group->id;
        $this->quickGroupName = $group->name;
        $this->quickGroupDescription = $group->description ?? '';
        $this->quickParentId = $group->parent_id;
        $this->quickCustomFieldValues = $this->loadCustomFieldValuesFrom($group);
        $this->quickGroupImageUrl = app(FileService::class)->signedUrlOrNull(
            $group->icon_thumb_url ?: $group->icon_url
        );
        $this->dispatch('product-group-quick-edit-loaded');
    }

    public function closeQuickCreate(): void
    {
        $this->clearQuickGroupDropzone();
        $this->resetQuickCreate();
    }

    public function clearQuickGroupDropzone(): void
    {
        if ($this->quickGroupDropzone instanceof TemporaryUploadedFile) {
            $temporaryFile = $this->quickGroupDropzone;
            $temporaryFile->delete();

            Storage::disk(FileUploadConfiguration::disk())->delete(
                FileUploadConfiguration::path($temporaryFile->getFilename().'.json')
            );
        }

        $this->quickGroupDropzone = null;
    }

    public function removeQuickGroupImage(): void
    {
        if ($this->quickGroupDropzone instanceof TemporaryUploadedFile) {
            $this->clearQuickGroupDropzone();

            return;
        }

        if ($this->quickGroupId === null) {
            $this->quickGroupImageUrl = null;

            return;
        }

        $group = ProductGroup::findOrFail($this->quickGroupId);
        $storageDisk = config('filesystems.default', 'local') === 'local'
            ? 'public'
            : config('filesystems.default');

        foreach ([$group->icon_url, $group->icon_thumb_url] as $path) {
            if ($path) {
                Storage::disk($storageDisk)->delete($path);
            }
        }

        $group->update([
            'icon_url' => null,
            'icon_thumb_url' => null,
        ]);

        $this->quickGroupImageUrl = null;
    }

    /**
     * Active Product Group custom fields used by both create workflows.
     *
     * @return Collection<string, Collection<int, CustomField>>
     */
    #[Computed]
    public function groupedCustomFields(): Collection
    {
        return CustomField::query()
            ->forModule('ProductGroup')
            ->active()
            ->with(['group', 'listName.values'])
            ->orderBy('sort_order')
            ->get()
            ->groupBy(fn (CustomField $field): string => $field->group?->name ?? 'General');
    }

    /**
     * @return array<int|string, string>
     */
    private function parentOptionsForView(): array
    {
        return $this->parentOptionsCache ??= ProductGroup::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function deleteGroup(int $groupId): void
    {
        $group = ProductGroup::findOrFail($groupId);
        (new \App\Actions\Products\DeleteProductGroup)($group);
        $this->dispatch('group-deleted');
    }

    /**
     * Bulk-delete the selected product groups.
     *
     * @param  array<int, int>  $ids
     */
    public function deleteSelected(array $ids): void
    {
        $action = new \App\Actions\Products\DeleteProductGroup;

        foreach ($ids as $id) {
            $group = ProductGroup::find($id);

            if ($group) {
                $action($group);
            }
        }

        $this->dispatch('group-deleted');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $parentOptions = $this->parentOptionsForView();

        return [
            'parentOptions' => $parentOptions,
            'groupedCustomFields' => $this->groupedCustomFields,
            'columns' => [
                ['key' => 'checkbox', 'type' => 'checkbox'],
                ['key' => 'name', 'label' => __('Name'), 'sortable' => true, 'filterable' => true, 'filter_type' => 'text', 'view' => 'livewire.product-groups.partials.column-name'],
                ['key' => 'description', 'label' => __('Description')],
                ['key' => 'parent_id', 'label' => __('Parent Group'), 'sortable' => true, 'filterable' => true, 'filter_type' => 'select', 'filter_options' => $parentOptions, 'view' => 'livewire.product-groups.partials.column-parent'],
                ['key' => 'products_count', 'label' => __('Products'), 'sortable' => true, 'view' => 'livewire.product-groups.partials.column-products-count'],
                ['key' => 'created_at', 'label' => __('Created'), 'sortable' => true],
                ['key' => 'actions', 'type' => 'actions'],
            ],
        ];
    }
}; ?>

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
                :model="\App\Models\ProductGroup::class"
                :searchable="['name']"
                :with="['parent']"
                :with-counts="['products']"
                :refresh-events="['group-deleted', 'group-created', 'group-updated']"
                default-sort="name"
                empty-message="No product groups found."
                actions-view="livewire.product-groups.partials.row-actions"
                bulk-actions-view="livewire.product-groups.partials.bulk-actions"
                toolbar-view="livewire.product-groups.partials.toolbar"
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
