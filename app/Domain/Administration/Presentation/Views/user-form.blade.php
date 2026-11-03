<section class="w-full min-w-0">
    <x-admin.layout group="users" title="Edit User" description="Update user details, roles, and manage account actions.">
        <x-slot:breadcrumbs>
            <x-signals.breadcrumb :items="[
                ['label' => 'Users', 'href' => route('admin.settings.users')],
                ['label' => 'Edit User'],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <flux:button variant="ghost" href="{{ route('admin.settings.users') }}" wire:navigate.hover>
                Back to Users
            </flux:button>
        </x-slot:actions>

        <form wire:submit="save" class="space-y-8">
            {{-- User Details --}}
            <x-signals.form-section title="User Details">
                <div class="space-y-4">
                    <flux:input wire:model="userName" label="Name" required />
                    <flux:input wire:model="userEmail" label="Email" type="email" required />
                </div>
            </x-signals.form-section>

            {{-- Roles --}}
            <x-signals.form-section title="Roles">
                @if($isOwner)
                    <p class="text-sm text-zinc-500 mb-3">Owners have implicit access to everything. Additional roles are optional.</p>
                @endif
                <div class="space-y-2">
                    @foreach($availableRoles as $role)
                        <label class="flex items-center gap-2 cursor-pointer" wire:key="role-{{ $role->id }}"
                               x-data="{ checked: @js(in_array($role->name, $selectedRoles)) }"
                               x-init="$watch('$wire.selectedRoles', v => checked = v.includes('{{ $role->name }}'))">
                            <input type="checkbox" wire:model="selectedRoles" value="{{ $role->name }}" class="hidden" x-on:change="checked = $el.checked" />
                            <x-signals.checkbox x-bind:class="checked && 'checked'" />
                            <span class="text-sm">{{ $role->name }}</span>
                            @if($role->description)
                                <span class="text-xs text-zinc-500">- {{ $role->description }}</span>
                            @endif
                        </label>
                    @endforeach
                </div>
                @error('selectedRoles') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">Save Changes</flux:button>
                <flux:button variant="ghost" href="{{ route('admin.settings.users') }}" wire:navigate.hover>Cancel</flux:button>
            </div>
        </form>

        {{-- Account Actions --}}
        @unless($isOwner)
            <x-signals.form-section title="Account Actions">
                <div class="flex flex-wrap gap-3">
                    @if($isInvited)
                        <flux:button variant="ghost" wire:click="resendInvitation">Resend Invitation</flux:button>
                    @endif

                    @if(! $isDeactivated && $hasPassword)
                        <flux:button variant="ghost" wire:click="sendPasswordReset">Send Password Reset</flux:button>
                    @endif

                    @if($isDeactivated)
                        <flux:button variant="ghost" wire:click="reactivate">Reactivate User</flux:button>
                    @else
                        <flux:button variant="danger" wire:click="$set('confirmingDeactivation', {{ $userId }})">Deactivate User</flux:button>
                    @endif

                    @can('users.delete')
                        <flux:button variant="danger" wire:click="$set('confirmingDeletion', {{ $userId }})">Delete User</flux:button>
                    @endcan
                </div>
            </x-signals.form-section>
        @endunless

        <x-action-message on="user-deactivated">User deactivated.</x-action-message>
        <x-action-message on="user-reactivated">User reactivated.</x-action-message>
        <x-action-message on="password-reset-sent">Password reset email sent.</x-action-message>
        <x-action-message on="invitation-resent">Invitation resent.</x-action-message>

        {{-- Deactivation Confirmation --}}
        @if($confirmingDeactivation)
            <flux:modal wire:model.self="confirmingDeactivation">
                <div class="space-y-4">
                    <flux:heading size="lg">Deactivate User</flux:heading>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        Are you sure you want to deactivate this user? They will no longer be able to log in and all their API tokens will be revoked.
                    </p>
                    <div class="flex justify-end gap-3">
                        <flux:button variant="ghost" wire:click="$set('confirmingDeactivation', null)">Cancel</flux:button>
                        <flux:button variant="danger" wire:click="deactivate">Deactivate</flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif

        {{-- Deletion Confirmation --}}
        @if($confirmingDeletion)
            <flux:modal wire:model.self="confirmingDeletion">
                <div class="space-y-4">
                    <flux:heading size="lg">Delete User</flux:heading>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        Are you sure you want to permanently delete this user? This action cannot be undone. All API tokens will be revoked and the user record will be removed.
                    </p>
                    <div class="flex justify-end gap-3">
                        <flux:button variant="ghost" wire:click="$set('confirmingDeletion', null)">Cancel</flux:button>
                        <flux:button variant="danger" wire:click="deleteUser">Delete Permanently</flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif
    </x-admin.layout>
</section>
