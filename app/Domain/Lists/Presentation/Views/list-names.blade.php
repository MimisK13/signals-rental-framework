<section class="w-full">
    <x-admin.layout
        group="data"
        title="List Names"
        description="Manage configurable list definitions."
    >
        <x-slot:actions>
            <flux:button
                variant="primary"
                :href="route('admin.settings.list-names.create')"
                wire:navigate.hover
            >
                Add List
            </flux:button>
        </x-slot:actions>

        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Structure</th>
                        <th>System</th>
                        <th>Values</th>
                        <th class="w-40">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($listNames as $listName)
                        <tr wire:key="list-{{ $listName->id }}">
                            <td class="font-medium">{{ $listName->name }}</td>
                            <td class="text-sm text-zinc-500">{{ $listName->description ?? '—' }}</td>
                            <td>
                                @if ($listName->is_hierarchical)
                                    <span class="s-badge s-badge-blue">Hierarchical</span>
                                @else
                                    <span class="s-badge s-badge-zinc">Flat</span>
                                @endif
                            </td>
                            <td>
                                @if ($listName->is_system)
                                    <span class="inline-flex items-center gap-1">
                                        <flux:icon.lock-closed class="size-4 text-zinc-400" />
                                        <span class="s-badge s-badge-zinc">System</span>
                                    </span>
                                @endif
                            </td>
                            <td class="tabular-nums">{{ $listName->values_count }}</td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        :href="route('admin.settings.lists', $listName)"
                                        wire:navigate.hover
                                    >
                                        Manage values
                                    </flux:button>

                                    @if (!$listName->is_system)
                                        <flux:dropdown>
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="ellipsis-horizontal"
                                                aria-label="Actions for {{ $listName->name }}"
                                            />
                                            <flux:menu>
                                                <flux:menu.item
                                                    :href="route('admin.settings.list-names.edit', $listName)"
                                                    wire:navigate.hover
                                                >
                                                    Edit
                                                </flux:menu.item>
                                                <flux:menu.item
                                                    variant="danger"
                                                    wire:click="deleteList({{ $listName->id }})"
                                                    wire:confirm="Delete {{ $listName->name }} and all its values? This cannot be undone."
                                                >
                                                    Delete
                                                </flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="6"
                                class="p-6 text-center text-[var(--text-muted)]"
                            >
                                No lists found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @error('delete')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror

        <x-action-message on="list-deleted">List deleted.</x-action-message>
    </x-admin.layout>
</section>
