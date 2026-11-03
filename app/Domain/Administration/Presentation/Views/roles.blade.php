<section class="w-full min-w-0">
    <x-admin.layout group="users" title="Roles" description="Manage roles and their permissions.">
        <x-slot:actions>
            <flux:button variant="primary" href="{{ route('admin.settings.roles.create') }}" wire:navigate.hover>Create Role</flux:button>
        </x-slot:actions>

        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Description</th>
                        <th>Users</th>
                        <th>Type</th>
                        <th class="w-12"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($roles as $role)
                        <tr wire:key="role-{{ $role->id }}">
                            <td class="font-medium">{{ $role->name }}</td>
                            <td class="text-sm text-zinc-500">{{ $role->description ?? '-' }}</td>
                            <td>{{ $role->users_count }}</td>
                            <td>
                                @if($role->is_system)
                                    <span class="s-badge s-badge-blue">System</span>
                                @else
                                    <span class="s-badge">Custom</span>
                                @endif
                            </td>
                            <td class="w-12 text-right">
                                <div
                                    x-data="{
                                        open: false,
                                        toggle() { this.open ? this.close(this.$refs.button) : (this.open = true) },
                                        close(focusAfter) {
                                            if (! this.open) return
                                            this.open = false
                                            focusAfter && focusAfter.focus()
                                        }
                                    }"
                                    x-on:keydown.escape.prevent.stop="close($refs.button)"
                                    x-on:focusin.window="! $refs.panel.contains($event.target) && close()"
                                    x-id="['role-actions']"
                                    class="relative inline-flex"
                                >
                                    <button x-ref="button" type="button" x-on:click="toggle()" :aria-expanded="open" :aria-controls="$id('role-actions')" class="s-btn s-btn-ghost s-btn-sm">
                                        <span class="sr-only">Actions for {{ $role->name }}</span>
                                        <flux:icon.ellipsis-horizontal class="size-4" />
                                    </button>
                                    <div x-ref="panel" x-show="open" x-transition.origin.top.right x-on:click.outside="close($refs.button)" :id="$id('role-actions')" x-cloak class="absolute right-0 top-full z-20 mt-1 min-w-40 rounded-md border border-zinc-200 bg-white p-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                                        <a href="{{ route('admin.settings.roles.edit', $role) }}" wire:navigate.hover x-on:click="close()" class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm text-zinc-700 hover:bg-zinc-50 focus-visible:bg-zinc-50 dark:text-zinc-200 dark:hover:bg-zinc-800 dark:focus-visible:bg-zinc-800">
                                            <flux:icon.pencil-square class="size-4" />
                                            Edit
                                        </a>
                                        @if(! $role->is_system)
                                            <button type="button" wire:click="$set('confirmingDeletion', {{ $role->id }})" x-on:click="close($refs.button)" class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm text-red-600 hover:bg-red-50 focus-visible:bg-red-50 dark:hover:bg-red-950 dark:focus-visible:bg-red-950">
                                                <flux:icon.trash class="size-4" />
                                                Delete
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-action-message on="role-deleted">Role deleted.</x-action-message>

        {{-- Delete Confirmation --}}
        @if($confirmingDeletion)
            <flux:modal wire:model.self="confirmingDeletion">
                <div class="space-y-4">
                    <flux:heading size="lg">Delete Role</flux:heading>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        Are you sure you want to delete this role? This action cannot be undone.
                    </p>
                    <div class="flex justify-end gap-3">
                        <flux:button variant="ghost" wire:click="$set('confirmingDeletion', null)">Cancel</flux:button>
                        <flux:button variant="danger" wire:click="deleteRole({{ $confirmingDeletion }})">Delete Role</flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif
    </x-admin.layout>
</section>
