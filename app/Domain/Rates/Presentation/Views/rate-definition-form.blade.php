<section class="w-full">
    <x-admin.layout group="pricing" :title="$isEditing ? 'Edit Rate Definition' : 'New Rate Definition'"
        description="Configure a composable pricing engine: a calculation strategy, base period, and optional modifiers.">
        <x-slot:breadcrumbs>
            <x-signals.breadcrumb :items="[
                ['label' => 'Rate Definitions', 'href' => route('admin.settings.rate-definitions')],
                ['label' => $isEditing ? 'Edit' : 'Create'],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <flux:button variant="ghost" href="{{ route('admin.settings.rate-definitions') }}" wire:navigate.hover>Back
            </flux:button>
        </x-slot:actions>

        @if (!$presetChosen)
            <x-signals.form-section title="Choose a starting point"
                description="Start from an industry-standard preset, or build your own.">
                <div class="s-admin-landing-grid">
                    @foreach ($presets as $preset)
                        <button type="button" wire:key="preset-{{ $preset['slug'] }}"
                            wire:click="choosePreset('{{ $preset['slug'] }}')" class="s-module-card enabled text-left">
                            <div class="s-module-icon">
                                <flux:icon.calculator class="!size-5" />
                            </div>
                            <div class="s-module-info">
                                <div class="s-module-name">{{ $preset['name'] }}</div>
                                <div class="s-module-desc">{{ $preset['description'] }}</div>
                            </div>
                        </button>
                    @endforeach
                    <button type="button" wire:click="fromScratch" class="s-module-card enabled text-left">
                        <div class="s-module-icon">
                            <flux:icon.plus class="!size-5" />
                        </div>
                        <div class="s-module-info">
                            <div class="s-module-name">From Scratch</div>
                            <div class="s-module-desc">Build a custom rate definition from the ground up.</div>
                        </div>
                    </button>
                </div>
            </x-signals.form-section>
        @else
            <form wire:submit="save" class="space-y-8">
                <x-signals.form-section title="Details">
                    <div class="space-y-4">
                        <flux:input wire:model="name" label="Name" required />
                        <flux:textarea wire:model="description" label="Description" rows="2" />
                    </div>
                </x-signals.form-section>

                <x-signals.form-section title="Engine"
                    description="The calculation strategy, base period, and which modifiers apply.">
                    <div class="space-y-4">
                        <flux:select wire:model.live="calculationStrategy" label="Calculation Strategy">
                            @foreach ($strategies as $strategy)
                                <flux:select.option value="{{ $strategy->identifier() }}">{{ $strategy->label() }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        @if (!empty($basePeriodOptions))
                            <flux:select wire:model="basePeriod" label="Base Period">
                                @foreach ($basePeriodOptions as $period)
                                    <flux:select.option value="{{ $period->value }}">{{ $period->label() }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        @endif

                        @if (!empty($availableModifiers))
                            <div class="s-field">
                                <label class="s-field-label">Modifiers</label>
                                <p class="s-field-help">Layer tiered duration multipliers and quantity factors on top of
                                    the base rate.</p>
                                <div class="mt-2 flex flex-col gap-2">
                                    @foreach ($availableModifiers as $modifier)
                                        <flux:checkbox wire:key="modifier-{{ $modifier->identifier() }}"
                                            wire:model.live="enabledModifiers" value="{{ $modifier->identifier() }}"
                                            :label="$modifier->label()" />
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </x-signals.form-section>

                @foreach ($sections as $section)
                    @php
                        $isOptions = $section->key === 'options';
                        $basePath = $isOptions ? 'strategyConfig' : 'modifierConfigs.' . $section->key;
                        $scopeValues = $isOptions ? $strategyConfig : $modifierConfigs[$section->key] ?? [];
                    @endphp
                    <x-signals.form-section :title="$section->label" wire:key="section-{{ $section->key }}">
                        <div class="space-y-4">
                            @foreach ($section->schema->fields() as $field)
                                <x-config-schema.field :field="$field->toArray()" :path="$basePath" :values="$scopeValues" />
                            @endforeach
                        </div>
                    </x-signals.form-section>
                @endforeach

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit">
                        {{ $isEditing ? 'Save Changes' : 'Create Rate Definition' }}</flux:button>
                    <flux:button variant="ghost" href="{{ route('admin.settings.rate-definitions') }}"
                        wire:navigate.hover>Cancel</flux:button>
                </div>
            </form>
        @endif
    </x-admin.layout>
</section>
