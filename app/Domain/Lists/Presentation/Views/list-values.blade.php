<section class="w-full">
    <x-admin.layout
        group="data"
        :title="$listName->name"
        :description="'Manage values for ' . $listName->name . '.'"
    >
        <x-slot:actions>
            <flux:button
                variant="ghost"
                :href="route('admin.settings.list-names')"
                wire:navigate.hover
            >
                Back to List Names
            </flux:button>
            <flux:button
                variant="primary"
                :href="route('admin.settings.list-values.create', $listName)"
                wire:navigate.hover
            >
                Add Value
            </flux:button>
        </x-slot:actions>

        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        @if ($listName->is_hierarchical)
                            <th>Parent</th>
                        @endif
                        <th>Sort Order</th>
                        <th>System</th>
                        <th>Active</th>
                        <th class="w-12"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($values as $value)
                        <tr wire:key="value-{{ $value->id }}">
                            <td class="font-medium">{{ $value->name }}</td>
                            @if ($listName->is_hierarchical)
                                <td class="text-sm text-zinc-500">{{ $value->parent?->name ?? '—' }}</td>
                            @endif
                            <td class="tabular-nums">{{ $value->sort_order }}</td>
                            <td>
                                @if ($value->is_system)
                                    <span class="inline-flex items-center gap-1">
                                        <flux:icon.lock-closed class="size-4 text-zinc-400" />
                                        <span class="s-badge s-badge-zinc">System</span>
                                    </span>
                                @endif
                            </td>
                            <td>
                                <button
                                    wire:click="toggleActive({{ $value->id }})"
                                    class="s-btn-ghost s-btn-xs"
                                    aria-label="Toggle {{ $value->name }} active status"
                                >
                                    @if ($value->is_active)
                                        <span class="s-badge s-badge-green">Active</span>
                                    @else
                                        <span class="s-badge s-badge-zinc">Inactive</span>
                                    @endif
                                </button>
                            </td>
                            <td>
                                @if (!$value->is_system)
                                    <flux:dropdown>
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="ellipsis-horizontal"
                                            aria-label="Actions for {{ $value->name }}"
                                        />
                                        <flux:menu>
                                            <flux:menu.item
                                                :href="route('admin.settings.list-values.edit', [
                                                    $listName,
                                                    $value,
                                                ])"
                                                wire:navigate.hover
                                            >
                                                Edit
                                            </flux:menu.item>
                                            <flux:menu.item
                                                variant="danger"
                                                wire:click="deleteValue({{ $value->id }})"
                                                wire:confirm="Delete {{ $value->name }}? This cannot be undone."
                                            >
                                                Delete
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="{{ $listName->is_hierarchical ? 6 : 5 }}"
                                class="p-6 text-center text-[var(--text-muted)]"
                            >
                                No values found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @error('delete')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror

        <x-action-message on="value-deleted">Value deleted.</x-action-message>
    </x-admin.layout>
</section>
