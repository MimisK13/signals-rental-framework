<section class="w-full">
    <x-admin.layout
        group="data"
        :title="$isEditing ? 'Edit Value' : 'Add Value'"
        :description="$isEditing ? 'Update value details.' : 'Add a new value to ' . $listName->name . '.'"
    >
        <x-slot:breadcrumbs>
            <x-signals.breadcrumb :items="[
                ['label' => 'List Names', 'href' => route('admin.settings.list-names')],
                ['label' => $listName->name, 'href' => route('admin.settings.lists', $listName)],
                ['label' => $isEditing ? 'Edit' : 'Add'],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <flux:button
                variant="ghost"
                :href="route('admin.settings.lists', $listName)"
                wire:navigate.hover
            >
                Back to {{ $listName->name }}
            </flux:button>
        </x-slot:actions>

        <form
            wire:submit="save"
            class="space-y-8"
        >
            <x-signals.form-section title="Value Details">
                <div class="space-y-4">
                    <flux:input
                        wire:model="name"
                        label="Name"
                        required
                    />
                    <flux:input
                        wire:model="sortOrder"
                        label="Sort Order"
                        type="number"
                        min="0"
                    />
                    <flux:checkbox
                        wire:model="isActive"
                        label="Active"
                    />

                    @if ($listName->is_hierarchical)
                        <flux:select
                            wire:model="parentId"
                            label="Parent"
                            placeholder="None (top level)"
                        >
                            @foreach ($parentOptions as $option)
                                <flux:select.option value="{{ $option->id }}">
                                    {{ $option->name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                </div>
            </x-signals.form-section>

            <div class="flex flex-wrap items-center gap-4">
                <flux:button
                    variant="primary"
                    type="submit"
                >
                    {{ $isEditing ? 'Save Changes' : 'Add Value' }}
                </flux:button>
                <flux:button
                    variant="ghost"
                    :href="route('admin.settings.lists', $listName)"
                    wire:navigate.hover
                >
                    Cancel
                </flux:button>
            </div>
        </form>
    </x-admin.layout>
</section>
