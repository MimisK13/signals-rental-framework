<section class="w-full">
    <x-admin.layout
        group="data"
        :title="$isEditing ? 'Edit List' : 'Create List'"
        :description="$isEditing ? 'Update list details.' : 'Create a new configurable list.'"
    >
        <x-slot:breadcrumbs>
            <x-signals.breadcrumb :items="[
                ['label' => 'List Names', 'href' => route('admin.settings.list-names')],
                ['label' => $isEditing ? 'Edit' : 'Create'],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <flux:button
                variant="ghost"
                :href="route('admin.settings.list-names')"
                wire:navigate.hover
            >
                Back to List Names
            </flux:button>
        </x-slot:actions>

        <form
            wire:submit="save"
            class="space-y-8"
        >
            <x-signals.form-section title="List Details">
                <div class="space-y-4">
                    <flux:input
                        wire:model="name"
                        label="Name"
                        required
                    />
                    <flux:textarea
                        wire:model="description"
                        label="Description"
                        rows="3"
                    />

                    <div>
                        <flux:checkbox
                            wire:model="isHierarchical"
                            label="Hierarchical"
                            :disabled="$isEditing && $hasValues"
                        />
                        @if ($isEditing && $hasValues)
                            <p class="mt-1 text-xs text-zinc-500">
                                Cannot change hierarchy setting while values exist.
                            </p>
                        @endif
                    </div>
                </div>
            </x-signals.form-section>

            <div class="flex flex-wrap items-center gap-4">
                <flux:button
                    variant="primary"
                    type="submit"
                >
                    {{ $isEditing ? 'Save Changes' : 'Create List' }}
                </flux:button>
                <flux:button
                    variant="ghost"
                    :href="route('admin.settings.list-names')"
                    wire:navigate.hover
                >
                    Cancel
                </flux:button>
            </div>
        </form>
    </x-admin.layout>
</section>
