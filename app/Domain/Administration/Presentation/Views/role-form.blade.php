<section class="w-full min-w-0">
    <x-admin.layout group="users" :title="$isEditing ? 'Edit Role' : 'Create Role'" :description="$isEditing ? 'Update role details and permissions.' : 'Create a new role with specific permissions.'">
        <x-slot:breadcrumbs>
            <x-signals.breadcrumb :items="[
                ['label' => 'Roles', 'href' => route('admin.settings.roles')],
                ['label' => $isEditing ? 'Edit' : 'Create'],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <flux:button variant="ghost" href="{{ route('admin.settings.roles') }}" wire:navigate.hover>
                Back to Roles
            </flux:button>
        </x-slot:actions>

        <form wire:submit="save" class="space-y-8">
            <x-signals.form-section title="Role Details">
                <div class="space-y-4">
                    <flux:input wire:model="roleName" label="Role Name" required
                                :disabled="$isSystem" />
                    <flux:textarea wire:model="roleDescription" label="Description" rows="2" />
                </div>
            </x-signals.form-section>

            {{-- Users --}}
            <x-signals.form-section title="Users">
                <p class="text-sm text-zinc-500 mb-3">Select which users should have this role.</p>
                @if($allUsers->isEmpty())
                    <p class="text-sm text-zinc-400">No users available.</p>
                @else
                    <div class="space-y-2 max-h-64 overflow-y-auto">
                        @foreach($allUsers as $user)
                            <label class="flex items-center gap-2 cursor-pointer" wire:key="user-{{ $user->id }}"
                                   x-data="{ checked: @js(in_array($user->id, $selectedUsers)) }"
                                   x-init="$watch('$wire.selectedUsers', v => checked = v.includes({{ $user->id }}))">
                                <input type="checkbox" wire:model="selectedUsers" value="{{ $user->id }}" class="hidden" x-on:change="checked = $el.checked" />
                                <x-signals.checkbox x-bind:class="checked && 'checked'" />
                                <span class="text-sm">{{ $user->name }}</span>
                                @if($user->is_owner)
                                    <span class="s-status s-status-amber text-xs">Owner</span>
                                @endif
                                <span class="text-xs text-zinc-400">{{ $user->email }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
                @error('selectedUsers') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                @error('selectedUsers.*') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </x-signals.form-section>

            <div class="space-y-6">
                @foreach($permissionGroups as $group => $permissions)
                    <x-signals.form-section wire:key="group-{{ $group }}">
                        <x-slot:headerActions>
                            <button type="button" class="s-btn s-btn-ghost s-btn-sm"
                                    wire:click="toggleGroupPermissions('{{ $group }}')">
                                Toggle All
                            </button>
                        </x-slot:headerActions>

                        <div class="s-form-section-title mb-3">{{ $group }}</div>

                        <div class="grid grid-cols-2 gap-2">
                            @foreach($permissions as $key => $meta)
                                <label class="flex items-center gap-2 cursor-pointer" wire:key="perm-{{ $key }}"
                                       x-data="{ checked: @js(in_array($key, $selectedPermissions)) }"
                                       x-init="$watch('$wire.selectedPermissions', v => checked = v.includes('{{ $key }}'))">
                                    <input type="checkbox" wire:model="selectedPermissions" value="{{ $key }}" class="hidden" x-on:change="checked = $el.checked" />
                                    <x-signals.checkbox x-bind:class="checked && 'checked'" />
                                    <span class="text-sm">{{ $meta['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </x-signals.form-section>
                @endforeach
            </div>

            @error('selectedPermissions') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @error('selectedPermissions.*') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Changes' : 'Create Role' }}</flux:button>
                <flux:button variant="ghost" href="{{ route('admin.settings.roles') }}" wire:navigate.hover>Cancel</flux:button>
            </div>
        </form>
    </x-admin.layout>
</section>
