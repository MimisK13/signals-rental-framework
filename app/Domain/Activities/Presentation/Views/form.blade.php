<section class="w-full">
    @if ($isEditing)
        <x-dense::page-header title="Edit Activity">
            <x-slot:breadcrumbs>
                <a
                    href="{{ route('activities.index') }}"
                    wire:navigate.hover
                    class="text-[var(--link)] hover:underline"
                >Activities</a>
                <span class="mx-1 text-[var(--text-muted)]">/</span>
                <a
                    href="{{ route('activities.show', $activityId) }}"
                    wire:navigate.hover
                    class="text-[var(--link)] hover:underline"
                >{{ $subject }}</a>
                <span class="mx-1 text-[var(--text-muted)]">/</span>
                <span>Edit</span>
            </x-slot:breadcrumbs>
        </x-dense::page-header>
    @else
        <x-dense::page-header title="Create Activity">
            <x-slot:breadcrumbs>
                <a
                    href="{{ route('activities.index') }}"
                    wire:navigate.hover
                    class="text-[var(--link)] hover:underline"
                >Activities</a>
                <span class="mx-1 text-[var(--text-muted)]">/</span>
                <span>Create</span>
            </x-slot:breadcrumbs>
        </x-dense::page-header>
    @endif

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <form wire:submit="save">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; align-items: start;">
                {{-- LEFT COLUMN --}}
                <div class="space-y-6">
                    <x-signals.form-section title="Schedule">
                        <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                            <div>
                                <label class="s-field-label mb-1 block">Starts At</label>
                                <x-signals.datetime-input
                                    :value="$startsAt"
                                    placeholder="Select date & time"
                                    x-on:input="if (typeof $event.detail === 'string' || $event.detail === null) $wire.set('startsAt', $event.detail)"
                                />
                            </div>
                            <div>
                                <label class="s-field-label mb-1 block">Ends At</label>
                                <x-signals.datetime-input
                                    :value="$endsAt"
                                    placeholder="Select date & time"
                                    x-on:input="if (typeof $event.detail === 'string' || $event.detail === null) $wire.set('endsAt', $event.detail)"
                                />
                            </div>
                        </div>
                    </x-signals.form-section>

                    <x-signals.form-section title="Basic Info">
                        <div class="space-y-3">
                            <flux:input
                                wire:model="subject"
                                label="Subject"
                                required
                            />
                            <flux:textarea
                                wire:model="description"
                                label="Description"
                                rows="3"
                            />
                            <flux:input
                                wire:model="location"
                                label="Location"
                            />
                        </div>
                    </x-signals.form-section>

                    <x-signals.form-section title="Classification">
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <flux:select
                                    wire:model="typeId"
                                    label="Type"
                                >
                                    @foreach ($activityTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                                    @endforeach
                                </flux:select>

                                <flux:select
                                    wire:model="statusId"
                                    label="Status"
                                >
                                    @foreach ($activityStatuses as $status)
                                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                    @endforeach
                                </flux:select>
                            </div>

                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <flux:select
                                    wire:model="priority"
                                    label="Priority"
                                >
                                    @foreach ($activityPriorities as $p)
                                        <option value="{{ $p->value }}">{{ $p->label() }}</option>
                                    @endforeach
                                </flux:select>

                                <flux:select
                                    wire:model="timeStatus"
                                    label="Time Status"
                                >
                                    @foreach ($timeStatuses as $ts)
                                        <option value="{{ $ts->value }}">{{ $ts->label() }}</option>
                                    @endforeach
                                </flux:select>
                            </div>
                        </div>
                    </x-signals.form-section>

                    <div class="flex items-center gap-4 pt-2">
                        <flux:button
                            variant="primary"
                            type="submit"
                        >{{ $isEditing ? 'Save Changes' : 'Create Activity' }}</flux:button>
                        <flux:button
                            variant="ghost"
                            href="{{ $isEditing ? route('activities.show', $activityId) : route('activities.index') }}"
                            wire:navigate.hover
                        >Cancel</flux:button>
                    </div>
                </div>

                {{-- RIGHT COLUMN --}}
                <div
                    class="space-y-6"
                    style="position: sticky; top: 24px;"
                >
                    <x-signals.form-section title="Assignment">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-medium mb-1">Owner</label>
                                @if ($ownerSelectedName)
                                    <div
                                        class="flex items-center gap-2 rounded-lg border border-[var(--border)] bg-[var(--bg-secondary)] px-3 py-2">
                                        <span class="flex-1 truncate">{{ $ownerSelectedName }}</span>
                                        <button
                                            type="button"
                                            wire:click="clearOwner"
                                            class="text-[var(--text-muted)] hover:text-[var(--text-primary)] shrink-0"
                                        >&times;</button>
                                    </div>
                                @else
                                    <div
                                        x-data="{ open: false }"
                                        x-on:click.away="open = false"
                                        class="relative"
                                    >
                                        <flux:input
                                            wire:model.live.debounce.300ms="ownerSearch"
                                            placeholder="Search users..."
                                            x-on:focus="open = true"
                                            x-on:input="open = true"
                                            autocomplete="off"
                                        />
                                        @if ($this->ownerOptions->isNotEmpty())
                                            <div
                                                x-show="open"
                                                x-cloak
                                                class="absolute z-50 mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--bg-primary)] shadow-lg max-h-60 overflow-y-auto"
                                            >
                                                @foreach ($this->ownerOptions as $option)
                                                    <button
                                                        type="button"
                                                        wire:key="owner-{{ $option->id }}"
                                                        wire:click="selectOwner({{ $option->id }}, {{ \Illuminate\Support\Js::from($option->name) }})"
                                                        x-on:click="open = false"
                                                        class="block w-full px-3 py-2 text-left text-sm hover:bg-[var(--bg-secondary)] transition-colors"
                                                    >
                                                        {{ $option->name }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </x-signals.form-section>

                    <x-signals.form-section title="Regarding">
                        <div class="space-y-3">
                            <flux:select
                                wire:model.live="regardingType"
                                label="Entity Type"
                            >
                                <option value="">None</option>
                                <option value="Member">Member</option>
                                <option value="Product">Product</option>
                                <option value="StockLevel">Stock Level</option>
                            </flux:select>

                            @if ($regardingType)
                                <div>
                                    <label class="block text-sm font-medium mb-1">
                                        {{ match ($regardingType) {'Member' => 'Member','Product' => 'Product','StockLevel' => 'Stock Level',default => 'Entity'} }}
                                    </label>
                                    @if ($regardingSelectedName)
                                        <div
                                            class="flex items-center gap-2 rounded-lg border border-[var(--border)] bg-[var(--bg-secondary)] px-3 py-2">
                                            <span class="flex-1 truncate">{{ $regardingSelectedName }}</span>
                                            <button
                                                type="button"
                                                wire:click="clearRegarding"
                                                class="text-[var(--text-muted)] hover:text-[var(--text-primary)] shrink-0"
                                            >&times;</button>
                                        </div>
                                    @else
                                        <div
                                            x-data="{ open: false }"
                                            x-on:click.away="open = false"
                                            class="relative"
                                        >
                                            <flux:input
                                                wire:model.live.debounce.300ms="regardingSearch"
                                                placeholder="Search {{ strtolower(match ($regardingType) {'StockLevel' => 'stock levels','Member' => 'members','Product' => 'products',default => 'entities'}) }}..."
                                                x-on:focus="open = true"
                                                x-on:input="open = true"
                                                autocomplete="off"
                                            />
                                            @if ($this->regardingOptions->isNotEmpty())
                                                <div
                                                    x-show="open"
                                                    x-cloak
                                                    class="absolute z-50 mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--bg-primary)] shadow-lg max-h-60 overflow-y-auto"
                                                >
                                                    @foreach ($this->regardingOptions as $option)
                                                        <button
                                                            type="button"
                                                            wire:key="regarding-{{ $option->id }}"
                                                            wire:click="selectRegarding({{ $option->id }}, {{ \Illuminate\Support\Js::from($option->name) }})"
                                                            x-on:click="open = false"
                                                            class="block w-full px-3 py-2 text-left text-sm hover:bg-[var(--bg-secondary)] transition-colors"
                                                        >
                                                            {{ $option->name }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </x-signals.form-section>

                    @if ($groupedCustomFields->isNotEmpty())
                        <x-signals.form-section title="Custom Fields">
                            <x-signals.custom-fields-editor :groupedCustomFields="$groupedCustomFields" />
                        </x-signals.form-section>
                    @endif
                </div>
            </div>
        </form>
    </div>
</section>
