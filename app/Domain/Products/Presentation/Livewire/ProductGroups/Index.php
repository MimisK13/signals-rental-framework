<?php

declare(strict_types=1);

namespace App\Domain\Products\Presentation\Livewire\ProductGroups;

use App\Domain\CustomFields\Models\CustomField;
use App\Domain\CustomFields\Presentation\Concerns\LoadsCustomFieldValues;
use App\Domain\CustomFields\Presentation\Concerns\ReKeysCustomFieldErrors;
use App\Domain\Products\Actions\CreateProductGroup;
use App\Domain\Products\Actions\DeleteProductGroup;
use App\Domain\Products\Actions\UpdateProductGroup;
use App\Domain\Products\Data\CreateProductGroupData;
use App\Domain\Products\Data\UpdateProductGroupData;
use App\Domain\Products\Models\ProductGroup;
use App\Services\FileService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')] #[Title('Product Groups')] final class Index extends Component
{
    use LoadsCustomFieldValues;
    use ReKeysCustomFieldErrors;
    use WithFileUploads;

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
            ->toBase()
            ->groupBy(fn (CustomField $field): string => $field->group === null ? 'General' : $field->group->name);
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
        (new DeleteProductGroup)($group);
        $this->dispatch('group-deleted');
    }

    /**
     * Bulk-delete the selected product groups.
     *
     * @param  array<int, int>  $ids
     */
    public function deleteSelected(array $ids): void
    {
        $action = new DeleteProductGroup;

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
            'groupedCustomFields' => $this->groupedCustomFields(),
            'columns' => [
                ['key' => 'checkbox', 'type' => 'checkbox'],
                ['key' => 'name', 'label' => __('Name'), 'sortable' => true, 'filterable' => true, 'filter_type' => 'text', 'view' => 'products::product-groups.partials.column-name'],
                ['key' => 'description', 'label' => __('Description')],
                ['key' => 'parent_id', 'label' => __('Parent Group'), 'sortable' => true, 'filterable' => true, 'filter_type' => 'select', 'filter_options' => $parentOptions, 'view' => 'products::product-groups.partials.column-parent'],
                ['key' => 'products_count', 'label' => __('Products'), 'sortable' => true, 'view' => 'products::product-groups.partials.column-products-count'],
                ['key' => 'created_at', 'label' => __('Created'), 'sortable' => true],
                ['key' => 'actions', 'type' => 'actions'],
            ],
        ];
    }

    public function render(): View
    {
        return view('products::product-groups.index');
    }
}
