<section class="w-full">
    <x-admin.layout group="data" :title="$isEditing ? 'Edit Custom Field' : 'Create Custom Field'" :description="$isEditing ? 'Update custom field definition.' : 'Define a new custom field.'">
        <x-slot:breadcrumbs>
            <x-signals.breadcrumb :items="[
                ['label' => 'Custom Fields', 'href' => route('admin.settings.custom-fields')],
                ['label' => $isEditing ? 'Edit' : 'Create'],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <flux:button variant="ghost" href="{{ route('admin.settings.custom-fields') }}" wire:navigate.hover>
                Back to Custom Fields
            </flux:button>
        </x-slot:actions>

        <form wire:submit="save" class="space-y-8">
            {{-- Field Details --}}
            <x-signals.form-section title="Field Details">
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="name" label="Name" placeholder="po_reference" description="Lowercase identifier with underscores." required />
                    <flux:input wire:model="displayName" label="Display Name" placeholder="PO Reference" />
                </div>

                <div class="mt-4">
                    <flux:textarea wire:model="description" label="Description" rows="2" />
                </div>

                <div class="grid grid-cols-2 gap-4 mt-4">
                    <flux:select wire:model="moduleType" label="Module" required>
                        <flux:select.option value="">Select module...</flux:select.option>
                        @foreach ($moduleTypes as $moduleValue => $moduleLabel)
                            <flux:select.option value="{{ $moduleValue }}">{{ $moduleLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="fieldType" label="Field Type" required>
                        <flux:select.option value="">Select type...</flux:select.option>
                        @foreach ($fieldTypes as $type)
                            <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="mt-4">
                    <flux:select wire:model="customFieldGroupId" label="Group">
                        <flux:select.option value="">No group</flux:select.option>
                        @foreach ($groups as $group)
                            <flux:select.option value="{{ $group->id }}">{{ $group->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </x-signals.form-section>

            {{-- Configuration --}}
            <x-signals.form-section title="Configuration">
                @if ($showListNameField)
                    <div class="mb-4">
                        <flux:select wire:model="listNameId" label="List Name">
                            <flux:select.option value="">Select list...</flux:select.option>
                            @foreach ($listNames as $listName)
                                <flux:select.option value="{{ $listName->id }}">{{ $listName->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="sortOrder" label="Sort Order" type="number" />
                    <flux:input wire:model="defaultValue" label="Default Value" />
                </div>

                <div class="mt-4 space-y-3">
                    <flux:checkbox wire:model="isRequired" label="Required" />
                    <flux:checkbox wire:model="isSearchable" label="Searchable" />
                    <flux:checkbox wire:model="isActive" label="Active" />
                </div>
            </x-signals.form-section>

            {{-- Validation Rules --}}
            <x-signals.form-section title="Validation Rules">
                @if (count($validationKeys) === 0)
                    <flux:text variant="subtle">
                        @if ($fieldType === null)
                            Select a field type to configure validation rules.
                        @else
                            This field type has no configurable validation rules.
                        @endif
                    </flux:text>
                @else
                    <div class="grid grid-cols-2 gap-4">
                        @if (in_array('min_length', $validationKeys, true))
                            <flux:input wire:model="validationRules.min_length" label="Minimum Length" type="number" min="0" placeholder="No minimum" />
                        @endif
                        @if (in_array('max_length', $validationKeys, true))
                            <flux:input wire:model="validationRules.max_length" label="Maximum Length" type="number" min="0" placeholder="No maximum" />
                        @endif
                        @if (in_array('min', $validationKeys, true))
                            <flux:input wire:model="validationRules.min" label="Minimum Value" type="number" placeholder="No minimum" />
                        @endif
                        @if (in_array('max', $validationKeys, true))
                            <flux:input wire:model="validationRules.max" label="Maximum Value" type="number" placeholder="No maximum" />
                        @endif
                        @if (in_array('pattern', $validationKeys, true))
                            <flux:input wire:model="validationRules.pattern" label="Pattern (regex)" placeholder="/^[A-Z]{3}-\d+$/" description="A regular expression the value must match." class="col-span-2" />
                        @endif
                    </div>
                @endif
            </x-signals.form-section>

            {{-- Visibility Rules --}}
            <x-signals.form-section title="Visibility Rules">
                <x-slot:headerActions>
                    <flux:button type="button" size="sm" variant="ghost" icon="plus" wire:click="addVisibilityRule">
                        Add rule
                    </flux:button>
                </x-slot:headerActions>

                <flux:text variant="subtle" class="mb-4">
                    Show this field only when all rules match other fields on the same record. Leave empty to always show.
                </flux:text>

                @forelse ($visibilityRules as $index => $rule)
                    <div class="grid grid-cols-12 gap-3 items-end mb-3" wire:key="visibility-rule-{{ $index }}">
                        <div class="col-span-4">
                            <flux:input wire:model="visibilityRules.{{ $index }}.field" :label="$index === 0 ? 'Field' : null" placeholder="membership_type" />
                        </div>
                        <div class="col-span-3">
                            <flux:select wire:model.live="visibilityRules.{{ $index }}.operator" :label="$index === 0 ? 'Operator' : null">
                                @foreach ($visibilityOperators as $opValue => $opLabel)
                                    <flux:select.option value="{{ $opValue }}">{{ $opLabel }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div class="col-span-4">
                            @unless (in_array($rule['operator'] ?? 'eq', ['present', 'blank'], true))
                                <flux:input
                                    wire:model="visibilityRules.{{ $index }}.value"
                                    :label="$index === 0 ? 'Value' : null"
                                    :placeholder="in_array($rule['operator'] ?? 'eq', ['in', 'not_in'], true) ? 'Comma-separated list' : 'Value'"
                                />
                            @endunless
                        </div>
                        <div class="col-span-1">
                            <flux:button type="button" size="sm" variant="subtle" icon="trash" wire:click="removeVisibilityRule({{ $index }})" aria-label="Remove rule" />
                        </div>
                    </div>
                @empty
                    <flux:text variant="subtle">No visibility rules — this field is always shown.</flux:text>
                @endforelse
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Changes' : 'Create Custom Field' }}</flux:button>
                <flux:button variant="ghost" href="{{ route('admin.settings.custom-fields') }}" wire:navigate.hover>Cancel</flux:button>
            </div>
        </form>
    </x-admin.layout>
</section>
