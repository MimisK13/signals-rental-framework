@props([
    'groupedCustomFields',
    'prefix' => 'customFieldValues',
    'showRequired' => true,
    'nativeRequired' => true,
])

@if($groupedCustomFields->isNotEmpty())
    @foreach($groupedCustomFields as $groupName => $fields)
        <x-dense::card :title="$groupName === 'General' ? 'Custom Fields' : $groupName">
            <div class="space-y-3">
                @foreach($fields as $field)
                    @php
                        $cfLabel = $field->display_name ?? $field->name;
                        $fieldId = str_replace('.', '-', "{$prefix}-{$field->name}");
                        $errorKey = "{$prefix}.{$field->name}";
                        $errorId = "{$fieldId}-error";
                        $hasError = $errors->has($errorKey);
                        $inputType = match ($field->field_type) {
                            \App\Enums\CustomFieldType::Number,
                            \App\Enums\CustomFieldType::Currency,
                            \App\Enums\CustomFieldType::Percentage => 'number',
                            \App\Enums\CustomFieldType::Date => 'date',
                            \App\Enums\CustomFieldType::DateTime => 'datetime-local',
                            \App\Enums\CustomFieldType::Time => 'time',
                            \App\Enums\CustomFieldType::Email => 'email',
                            \App\Enums\CustomFieldType::Website => 'url',
                            \App\Enums\CustomFieldType::Telephone => 'tel',
                            \App\Enums\CustomFieldType::Colour => 'color',
                            default => 'text',
                        };
                        $inputPlaceholder = $field->field_type === \App\Enums\CustomFieldType::Website ? 'https://' : null;
                        $inputStep = in_array($field->field_type, [
                            \App\Enums\CustomFieldType::Number,
                            \App\Enums\CustomFieldType::Currency,
                            \App\Enums\CustomFieldType::Percentage,
                        ], true) ? 'any' : null;
                    @endphp

                    <div wire:key="cf-edit-{{ $field->id }}">
                        <x-dense::field
                            :label="$cfLabel"
                            :for="$fieldId"
                            :required="$showRequired && $field->is_required"
                            :error="$errors->first($errorKey)"
                            :error-id="$errorId"
                        >

                        @if($field->field_type === \App\Enums\CustomFieldType::Boolean)
                            <div class="flex items-center gap-2">
                                <input
                                    id="{{ $fieldId }}"
                                    type="checkbox"
                                    wire:model="{{ $prefix }}.{{ $field->name }}"
                                    @required($nativeRequired && $field->is_required)
                                    @if($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
                                    class="size-4 rounded border-[var(--erp-color-border)] text-[var(--blue)] focus:ring-[var(--blue)] @if($hasError) is-invalid @endif"
                                />
                            </div>
                        @elseif(in_array($field->field_type, [\App\Enums\CustomFieldType::Text, \App\Enums\CustomFieldType::RichText], true))
                            <x-dense::textarea
                                id="{{ $fieldId }}"
                                wire:model="{{ $prefix }}.{{ $field->name }}"
                                rows="2"
                                :required="$nativeRequired && $field->is_required"
                                :invalid="$hasError"
                                :aria-describedby="$hasError ? $errorId : null"
                            />
                        @elseif($field->field_type === \App\Enums\CustomFieldType::ListOfValues)
                            <x-dense::select
                                id="{{ $fieldId }}"
                                wire:model="{{ $prefix }}.{{ $field->name }}"
                                placeholder="Select {{ $cfLabel }}"
                                :required="$nativeRequired && $field->is_required"
                                :invalid="$hasError"
                                :aria-describedby="$hasError ? $errorId : null"
                            >
                                @if($field->listName)
                                    @foreach($field->listName->values->where('is_active', true)->sortBy('sort_order') as $lv)
                                        <option value="{{ $lv->id }}">{{ $lv->name }}</option>
                                    @endforeach
                                @endif
                            </x-dense::select>
                        @else
                            <x-dense::input
                                id="{{ $fieldId }}"
                                wire:model="{{ $prefix }}.{{ $field->name }}"
                                :type="$inputType"
                                :placeholder="$inputPlaceholder"
                                :step="$inputStep"
                                :required="$nativeRequired && $field->is_required"
                                :invalid="$hasError"
                                :aria-describedby="$hasError ? $errorId : null"
                            />
                        @endif
                        </x-dense::field>
                    </div>
                @endforeach
            </div>
        </x-dense::card>
    @endforeach
@else
    <x-dense::card title="Custom Fields">
        <p class="text-sm text-[var(--text-muted)]">No custom fields configured.</p>
    </x-dense::card>
@endif
