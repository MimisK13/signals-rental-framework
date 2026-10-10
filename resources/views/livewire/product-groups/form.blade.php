<?php

/*
 * ARCHIVED: Product Group create/edit is now handled by the index side panel.
 * This implementation is retained for reference and rollback, but is no longer routed.
 */

use App\Actions\Products\CreateProductGroup;
use App\Actions\Products\UpdateProductGroup;
use App\Data\Products\CreateProductGroupData;
use App\Data\Products\UpdateProductGroupData;
use App\Livewire\Concerns\LoadsCustomFieldValues;
use App\Livewire\Concerns\ReKeysCustomFieldErrors;
use App\Models\CustomField;
use App\Models\ProductGroup;
use App\Services\FileService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] class extends Component
{
    use LoadsCustomFieldValues;
    use ReKeysCustomFieldErrors;
    use WithFileUploads;

    public ?int $groupId = null;

    public string $name = '';

    public string $description = '';

    public ?int $parentId = null;

    /** @var array<string, mixed> */
    public array $customFieldValues = [];

    #[Validate('nullable|image|max:2048|mimes:jpeg,jpg,png,webp,gif')]
    public mixed $photo = null;

    public ?string $imageUrl = null;

    public function mount(?ProductGroup $productGroup = null): void
    {
        if ($productGroup?->exists) {
            $this->groupId = $productGroup->id;
            $this->name = $productGroup->name;
            $this->description = $productGroup->description ?? '';
            $this->parentId = $productGroup->parent_id;
            $this->customFieldValues = $this->loadCustomFieldValuesFrom($productGroup);
            $this->imageUrl = app(FileService::class)->signedUrlOrNull(
                $productGroup->icon_thumb_url ?: $productGroup->icon_url
            );
        }
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'parentId' => ['nullable', 'integer', 'exists:product_groups,id'],
            'photo' => ['nullable', 'image', 'max:2048', 'mimes:jpeg,jpg,png,webp,gif'],
        ]);

        try {
            if ($this->groupId) {
                $group = ProductGroup::findOrFail($this->groupId);
                $result = (new UpdateProductGroup)(
                    $group,
                    UpdateProductGroupData::from([
                        'name' => $this->name,
                        'description' => $this->description ?: null,
                        'parent_id' => $this->parentId,
                        'custom_fields' => $this->customFieldValues,
                    ])
                );
            } else {
                $result = (new CreateProductGroup)(
                    CreateProductGroupData::from([
                        'name' => $this->name,
                        'description' => $this->description ?: null,
                        'parent_id' => $this->parentId,
                        'custom_fields' => $this->customFieldValues,
                    ])
                );

            }

            if ($this->photo) {
                $group = ProductGroup::findOrFail($result->id);
                $upload = app(FileService::class)->uploadIcon($this->photo, $group);
                $group->update([
                    'icon_url' => $upload['icon_url'],
                    'icon_thumb_url' => $upload['icon_thumb_url'],
                ]);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->reKeyCustomFieldErrors($e, $this->referenceData->pluck('name')->all());
        } catch (\Throwable $e) {
            report($e);
            $this->addError('photo', 'Failed to upload image. Please try again.');

            return;
        }

        $this->redirect(route('product-groups.index'), navigate: true);
    }

    public function removeImage(): void
    {
        if ($this->photo instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            $this->photo = null;

            return;
        }

        if ($this->groupId === null) {
            $this->imageUrl = null;

            return;
        }

        $group = ProductGroup::findOrFail($this->groupId);
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
        $this->imageUrl = null;
    }

    /**
     * Active custom field definitions for the ProductGroup module.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, CustomField>
     */
    #[Computed]
    public function referenceData(): \Illuminate\Database\Eloquent\Collection
    {
        return CustomField::query()
            ->forModule('ProductGroup')
            ->active()
            ->with(['group', 'listName.values'])
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $customFields = $this->referenceData;

        return [
            'isEditing' => $this->groupId !== null,
            'group' => $this->groupId ? ProductGroup::find($this->groupId) : null,
            'parentOptions' => ProductGroup::query()
                ->when($this->groupId, fn ($q) => $q->where('id', '!=', $this->groupId))
                ->orderBy('name')
                ->get(['id', 'name']),
            'groupedCustomFields' => $customFields->groupBy(fn ($f) => $f->group?->name ?? 'General'),
        ];
    }
}; ?>

<section class="w-full">
    @if($isEditing)
        <x-dense::page-header title="Edit Product Group">
            <x-slot:breadcrumbs>
                <a href="{{ route('product-groups.index') }}" wire:navigate.hover class="text-[var(--link)] hover:underline">Product Groups</a>
                <span class="mx-1 text-[var(--text-muted)]">/</span>
                <a href="{{ route('products.index', ['filters' => ['product_group_id' => $groupId]]) }}" wire:navigate.hover class="text-[var(--link)] hover:underline">{{ $name }}</a>
                <span class="mx-1 text-[var(--text-muted)]">/</span>
                <span>Edit</span>
            </x-slot:breadcrumbs>
        </x-dense::page-header>
    @else
        <x-dense::page-header title="Create Product Group">
            <x-slot:breadcrumbs>
                <a href="{{ route('product-groups.index') }}" wire:navigate.hover class="text-[var(--link)] hover:underline">Product Groups</a>
                <span class="mx-1 text-[var(--text-muted)]">/</span>
                <span>Create</span>
            </x-slot:breadcrumbs>
        </x-dense::page-header>
    @endif

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <form wire:submit="save" class="max-w-[560px] space-y-4">
            <x-dense::field
                label="Group Image"
                for="product-group-photo"
                :error="$errors->first('photo')"
                error-id="product-group-photo-error"
            >
                <x-dense::dropzone
                    id="product-group-photo"
                    model="photo"
                    clear-method="removeImage"
                    :preview="$photo ? $photo->temporaryUrl() : $imageUrl"
                    accept="image/*"
                    placeholder="Drag an image here or click to browse."
                />
            </x-dense::field>

            <x-dense::field
                label="Name"
                for="product-group-name"
                required
                :error="$errors->first('name')"
                error-id="product-group-name-error"
            >
                <x-dense::input
                    id="product-group-name"
                    wire:model="name"
                    required
                    :invalid="$errors->has('name')"
                    :aria-describedby="$errors->has('name') ? 'product-group-name-error' : null"
                    autofocus
                    autocomplete="off"
                />
            </x-dense::field>

            <x-dense::field
                label="Description"
                for="product-group-description"
                :error="$errors->first('description')"
                error-id="product-group-description-error"
            >
                <x-dense::textarea
                    id="product-group-description"
                    wire:model="description"
                    :invalid="$errors->has('description')"
                    :aria-describedby="$errors->has('description') ? 'product-group-description-error' : null"
                    rows="3"
                />
            </x-dense::field>

            <x-dense::field
                label="Parent Group"
                for="product-group-parent"
                :error="$errors->first('parentId')"
                error-id="product-group-parent-error"
            >
                <x-dense::combobox
                    id="product-group-parent"
                    model="parentId"
                    :selected="$parentId"
                    :options="collect($parentOptions)->map(fn ($option) => ['id' => (int) $option->id, 'name' => $option->name])->values()->all()"
                    :limit="5"
                    :invalid="$errors->has('parentId')"
                    :aria-describedby="$errors->has('parentId') ? 'product-group-parent-error' : null"
                    placeholder="Select parent group"
                />
            </x-dense::field>

                @if($groupedCustomFields->isNotEmpty())
                    <x-signals.custom-fields-editor
                        :groupedCustomFields="$groupedCustomFields"
                        prefix="customFieldValues"
                        :nativeRequired="false"
                        dense
                    />
                @endif

                <div class="flex items-center gap-2 pt-2">
                    <x-dense::button type="submit" variant="accent">
                        {{ $isEditing ? 'Save Changes' : 'Create Group' }}
                    </x-dense::button>
                    <x-dense::button
                        type="button"
                        variant="ghost"
                        href="{{ route('product-groups.index') }}"
                        wire:navigate.hover
                    >
                        Cancel
                    </x-dense::button>
                </div>
        </form>
    </div>
</section>
