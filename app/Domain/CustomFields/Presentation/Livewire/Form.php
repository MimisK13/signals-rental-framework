<?php

declare(strict_types=1);

namespace App\Domain\CustomFields\Presentation\Livewire;

use App\Domain\CustomFields\Actions\CreateCustomField;
use App\Domain\CustomFields\Actions\UpdateCustomField;
use App\Domain\CustomFields\Data\CreateCustomFieldData;
use App\Domain\CustomFields\Data\UpdateCustomFieldData;
use App\Domain\CustomFields\Enums\CustomFieldType;
use App\Domain\CustomFields\Models\CustomField;
use App\Domain\CustomFields\Models\CustomFieldGroup;
use App\Domain\CustomFields\Services\CustomFieldModuleRegistry;
use App\Domain\Lists\Models\ListName;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Custom Field')]
final class Form extends Component
{
    public ?int $fieldId = null;

    public string $name = '';

    public string $displayName = '';

    public string $description = '';

    public string $moduleType = '';

    public ?int $fieldType = null;

    public ?int $customFieldGroupId = null;

    public ?int $listNameId = null;

    public int $sortOrder = 0;

    public bool $isRequired = false;

    public bool $isSearchable = false;

    public string $defaultValue = '';

    public bool $isActive = true;

    /**
     * Structured validation-rule inputs. Only the keys relevant to the selected
     * field type are persisted to the validation_rules JSONB column, in the
     * shape CustomFieldValidator consumes (min_length/max_length/pattern/min/max).
     *
     * @var array<string, string>
     */
    public array $validationRules = [
        'min_length' => '',
        'max_length' => '',
        'pattern' => '',
        'min' => '',
        'max' => '',
    ];

    /**
     * Visibility-rule builder rows. Each row is {field, operator, value} and is
     * persisted to the visibility_rules JSONB column in the exact shape
     * VisibilityRuleEvaluator consumes.
     *
     * @var array<int, array{field: string, operator: string, value: string}>
     */
    public array $visibilityRules = [];

    public function mount(?CustomField $customField = null): void
    {
        if ($customField?->exists) {
            $this->fieldId = $customField->id;
            $this->name = $customField->name;
            $this->displayName = $customField->display_name ?? '';
            $this->description = $customField->description ?? '';
            $this->moduleType = $customField->module_type;
            $this->fieldType = $customField->field_type->value;
            $this->customFieldGroupId = $customField->custom_field_group_id;
            $this->listNameId = $customField->list_name_id;
            $this->sortOrder = $customField->sort_order ?? 0;
            $this->isRequired = $customField->is_required;
            $this->isSearchable = $customField->is_searchable;
            $this->defaultValue = $customField->default_value ?? '';
            $this->isActive = $customField->is_active;

            $this->loadValidationRules($customField->validation_rules ?? []);
            $this->loadVisibilityRules($customField->visibility_rules ?? []);
        }
    }

    /**
     * Populate the structured validation-rule inputs from stored JSONB.
     *
     * @param  array<string, mixed>  $rules
     */
    private function loadValidationRules(array $rules): void
    {
        foreach (array_keys($this->validationRules) as $key) {
            $this->validationRules[$key] = isset($rules[$key]) ? (string) $rules[$key] : '';
        }
    }

    /**
     * Populate the visibility-rule builder rows from stored JSONB.
     *
     * @param  array<int, array<string, mixed>>  $rules
     */
    private function loadVisibilityRules(array $rules): void
    {
        $this->visibilityRules = [];

        foreach ($rules as $rule) {
            $value = $rule['value'] ?? '';

            $this->visibilityRules[] = [
                'field' => (string) ($rule['field'] ?? ''),
                'operator' => (string) ($rule['operator'] ?? 'eq'),
                'value' => is_array($value) ? implode(', ', $value) : (string) $value,
            ];
        }
    }

    public function updatedFieldType(): void
    {
        if (! in_array($this->fieldType, [CustomFieldType::ListOfValues->value, CustomFieldType::MultiListOfValues->value])) {
            $this->listNameId = null;
        }
    }

    public function addVisibilityRule(): void
    {
        $this->visibilityRules[] = ['field' => '', 'operator' => 'eq', 'value' => ''];
    }

    public function removeVisibilityRule(int $index): void
    {
        unset($this->visibilityRules[$index]);
        $this->visibilityRules = array_values($this->visibilityRules);
    }

    /**
     * Build the validation_rules JSONB payload for the selected field type.
     *
     * Only keys appropriate to the field type and with a non-empty value are
     * included, matching the keys CustomFieldValidator reads. Numeric inputs are
     * cast to int/float; pattern stays a string. Returns null when no rules set.
     *
     * @return array<string, mixed>|null
     */
    private function buildValidationRules(): ?array
    {
        $allowed = $this->allowedValidationKeys();
        $rules = [];

        foreach ($allowed as $key) {
            $raw = trim($this->validationRules[$key] ?? '');

            if ($raw === '') {
                continue;
            }

            $rules[$key] = match ($key) {
                'min_length', 'max_length' => (int) $raw,
                'min', 'max' => is_numeric($raw) && ! str_contains($raw, '.') ? (int) $raw : (float) $raw,
                default => $raw,
            };
        }

        return $rules === [] ? null : $rules;
    }

    /**
     * The validation-rule keys applicable to the currently selected field type.
     *
     * @return list<string>
     */
    private function allowedValidationKeys(): array
    {
        return match ($this->fieldType) {
            CustomFieldType::String->value => ['min_length', 'max_length', 'pattern'],
            CustomFieldType::Text->value, CustomFieldType::RichText->value => ['max_length'],
            CustomFieldType::Number->value, CustomFieldType::Currency->value, CustomFieldType::Percentage->value => ['min', 'max'],
            CustomFieldType::Colour->value => ['pattern'],
            default => [],
        };
    }

    /**
     * Build the visibility_rules JSONB payload in the shape VisibilityRuleEvaluator
     * consumes: a list of {field, operator, value} objects. Rows without a field
     * are dropped. present/blank operators omit the value. in/not_in values are
     * split into an array on commas. Returns null when no rules set.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function buildVisibilityRules(): ?array
    {
        $rules = [];

        foreach ($this->visibilityRules as $row) {
            $field = trim($row['field']);

            if ($field === '') {
                continue;
            }

            $operator = $row['operator'];
            $rule = ['field' => $field, 'operator' => $operator];

            if (! in_array($operator, ['present', 'blank'], true)) {
                $rawValue = $row['value'];

                if (in_array($operator, ['in', 'not_in'], true)) {
                    $rule['value'] = array_values(array_filter(
                        array_map('trim', explode(',', $rawValue)),
                        fn (string $v): bool => $v !== '',
                    ));
                } else {
                    $rule['value'] = $rawValue;
                }
            }

            $rules[] = $rule;
        }

        return $rules === [] ? null : $rules;
    }

    public function save(): void
    {
        $listNameId = in_array($this->fieldType, [CustomFieldType::ListOfValues->value, CustomFieldType::MultiListOfValues->value])
            ? $this->listNameId
            : null;

        $validationRules = $this->buildValidationRules();
        $visibilityRules = $this->buildVisibilityRules();

        if ($this->fieldId) {
            $field = CustomField::findOrFail($this->fieldId);
            (new UpdateCustomField)($field, UpdateCustomFieldData::validateAndCreate([
                'name' => $this->name,
                'display_name' => $this->displayName ?: null,
                'description' => $this->description ?: null,
                'custom_field_group_id' => $this->customFieldGroupId,
                'list_name_id' => $listNameId,
                'sort_order' => $this->sortOrder,
                'is_required' => $this->isRequired,
                'is_searchable' => $this->isSearchable,
                'validation_rules' => $validationRules,
                'visibility_rules' => $visibilityRules,
                'default_value' => $this->defaultValue !== '' ? $this->defaultValue : null,
                'is_active' => $this->isActive,
            ]));
        } else {
            (new CreateCustomField)(CreateCustomFieldData::validateAndCreate([
                'name' => $this->name,
                'module_type' => $this->moduleType,
                'field_type' => $this->fieldType,
                'display_name' => $this->displayName ?: null,
                'description' => $this->description ?: null,
                'custom_field_group_id' => $this->customFieldGroupId,
                'list_name_id' => $listNameId,
                'sort_order' => $this->sortOrder,
                'is_required' => $this->isRequired,
                'is_searchable' => $this->isSearchable,
                'validation_rules' => $validationRules,
                'visibility_rules' => $visibilityRules,
                'default_value' => $this->defaultValue !== '' ? $this->defaultValue : null,
                'is_active' => $this->isActive,
            ]));
        }

        $this->redirect(route('admin.settings.custom-fields'), navigate: true);
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'isEditing' => $this->fieldId !== null,
            'groups' => CustomFieldGroup::query()->orderBy('name')->get(),
            'listNames' => ListName::query()->orderBy('name')->get(),
            'fieldTypes' => CustomFieldType::cases(),
            'moduleTypes' => app(CustomFieldModuleRegistry::class)->modules(),
            'showListNameField' => in_array($this->fieldType, [CustomFieldType::ListOfValues->value, CustomFieldType::MultiListOfValues->value]),
            'validationKeys' => $this->allowedValidationKeys(),
            'visibilityOperators' => [
                'eq' => 'Equals',
                'not_eq' => 'Does not equal',
                'in' => 'In list',
                'not_in' => 'Not in list',
                'present' => 'Is present',
                'blank' => 'Is blank',
                'gt' => 'Greater than',
                'gte' => 'Greater than or equal',
                'lt' => 'Less than',
                'lte' => 'Less than or equal',
                'contains' => 'Contains',
            ],
        ];
    }

    public function render(): View
    {
        return view('custom-fields::form', $this->with());
    }
}
