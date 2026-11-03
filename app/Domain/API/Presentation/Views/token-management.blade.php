<section class="w-full min-w-0">
    <x-admin.layout group="users" title="API Tokens" description="Create and manage personal access tokens for API authentication.">
        <x-slot:actions>
            <flux:button variant="primary" wire:click="openCreateModal">Create Token</flux:button>
        </x-slot:actions>

        {{-- Token Created Banner --}}
        @if($showTokenValue)
            <div class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950">
                <div class="flex items-start gap-3">
                    <flux:icon.check-circle class="w-5 h-5 text-green-600 dark:text-green-400 mt-0.5 shrink-0" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-green-800 dark:text-green-200">Token created successfully</p>
                        <p class="mt-1 text-xs text-green-700 dark:text-green-300">Copy this token now. It will not be shown again.</p>
                        <div class="mt-2 flex items-center gap-2">
                            <code class="block flex-1 rounded bg-green-100 px-3 py-2 font-mono text-sm text-green-900 dark:bg-green-900 dark:text-green-100 break-all">{{ $plainTextToken }}</code>
                            <button
                                x-data="{ copied: false }"
                                x-on:click="navigator.clipboard.writeText(@js($plainTextToken)); copied = true; setTimeout(() => copied = false, 2000)"
                                class="s-btn s-btn-sm s-btn-ghost shrink-0"
                                title="Copy to clipboard"
                            >
                                <template x-if="!copied"><flux:icon.clipboard class="w-4 h-4" /></template>
                                <template x-if="copied"><flux:icon.check class="w-4 h-4 text-green-600" /></template>
                            </button>
                        </div>
                        <div class="mt-2">
                            <flux:button variant="ghost" size="sm" wire:click="closeTokenDisplay">Dismiss</flux:button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Tokens Table --}}
        @if($tokens->isNotEmpty())
            <div class="s-table-wrap">
                <table class="s-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Abilities</th>
                            <th>Last Used</th>
                            <th>Created</th>
                            <th class="w-12"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tokens as $token)
                            <tr wire:key="token-{{ $token->id }}">
                                <td class="font-medium">{{ $token->name }}</td>
                                <td>
                                    @php $tokenAbilities = $token->abilities ?? []; @endphp
                                    <div x-data="{ open: false }" x-id="['token-scopes']" class="text-sm">
                                        <button type="button" class="flex items-center gap-1 text-zinc-600 hover:text-zinc-900 focus-visible:outline-2 focus-visible:outline-offset-2 dark:text-zinc-300 dark:hover:text-zinc-100" x-on:click="open = !open" :aria-expanded="open" :aria-controls="$id('token-scopes')">
                                            <flux:icon.chevron-right class="w-3.5 h-3.5 transition-transform" x-bind:class="open && 'rotate-90'" />
                                            {{ count($tokenAbilities) }} {{ \Illuminate\Support\Str::plural('scope', count($tokenAbilities)) }}
                                        </button>
                                        <div x-show="open" :id="$id('token-scopes')" x-cloak class="mt-1.5 space-y-0.5">
                                            @foreach($this->groupedAbilities($tokenAbilities) as $groupLabel => $actions)
                                                <div class="text-xs text-zinc-500">
                                                    <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $groupLabel }}:</span>
                                                    {{ implode(', ', $actions) }}
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </td>
                                <td class="text-sm text-zinc-500">
                                    {{ $token->last_used_at?->diffForHumans() ?? 'Never' }}
                                </td>
                                <td class="text-sm text-zinc-500">
                                    {{ $token->created_at->diffForHumans() }}
                                </td>
                                <td class="w-12 text-right">
                                    <div
                                        x-data="{
                                            open: false,
                                            toggle() {
                                                this.open ? this.close(this.$refs.button) : (this.open = true)
                                            },
                                            close(focusAfter) {
                                                if (! this.open) return
                                                this.open = false
                                                focusAfter && focusAfter.focus()
                                            }
                                        }"
                                        x-on:keydown.escape.prevent.stop="close($refs.button)"
                                        x-on:focusin.window="! $refs.panel.contains($event.target) && close()"
                                        x-id="['token-actions']"
                                        class="relative inline-flex"
                                    >
                                        <button x-ref="button" type="button" class="s-btn s-btn-ghost s-btn-sm" x-on:click="toggle()" :aria-expanded="open" :aria-controls="$id('token-actions')">
                                            <span class="sr-only">Actions for {{ $token->name }}</span>
                                            <flux:icon.ellipsis-horizontal class="size-4" />
                                        </button>
                                        <div x-ref="panel" x-show="open" x-transition.origin.top.right x-on:click.outside="close($refs.button)" :id="$id('token-actions')" x-cloak class="absolute right-0 top-full z-20 mt-1 min-w-40 rounded-md border border-zinc-200 bg-white p-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                                            <button type="button" wire:click="openEditModal({{ $token->id }})" x-on:click="close($refs.button)" class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm text-zinc-700 hover:bg-zinc-50 focus-visible:bg-zinc-50 dark:text-zinc-200 dark:hover:bg-zinc-800 dark:focus-visible:bg-zinc-800">
                                                <flux:icon.pencil-square class="size-4" />
                                                Edit scopes
                                            </button>
                                            <button type="button" wire:click="$set('confirmingRevocation', {{ $token->id }})" x-on:click="close($refs.button)" class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm text-red-600 hover:bg-red-50 focus-visible:bg-red-50 dark:hover:bg-red-950 dark:focus-visible:bg-red-950">
                                                <flux:icon.trash class="size-4" />
                                                Revoke token
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-8 text-center dark:border-zinc-700 dark:bg-zinc-800/50">
                <flux:icon.key class="mx-auto h-10 w-10 text-zinc-400" />
                <p class="mt-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">No API tokens</p>
                <p class="mt-1 text-sm text-zinc-500">Create a token to authenticate with the API.</p>
            </div>
        @endif

        <x-action-message on="token-created">Token created successfully.</x-action-message>
        <x-action-message on="token-updated">Token scopes updated.</x-action-message>
        <x-action-message on="token-revoked">Token revoked.</x-action-message>

        {{-- Create Token Modal --}}
        <flux:modal wire:model="showCreateModal">
            <div class="space-y-6">
                <flux:heading size="lg">Create API Token</flux:heading>

                <form wire:submit="createToken" class="space-y-4">
                    <flux:input wire:model="tokenName" label="Token Name" placeholder="e.g. CI/CD Pipeline" required />

                    <div>
                        @include('api::partials.ability-picker', ['model' => 'selectedAbilities', 'groups' => $abilityGroups])
                        @error('selectedAbilities') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-3">
                        <flux:button variant="ghost" wire:click="$set('showCreateModal', false)">Cancel</flux:button>
                        <flux:button variant="primary" type="submit">Create Token</flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>

        {{-- Edit Token Scopes Modal --}}
        <flux:modal wire:model="showEditModal">
            <div class="space-y-6">
                <flux:heading size="lg">Edit Token Scopes</flux:heading>
                <flux:text class="text-sm text-zinc-500">Update the abilities for this token. The token value itself does not change.</flux:text>

                <form wire:submit="updateToken" class="space-y-4">
                    <div>
                        @include('api::partials.ability-picker', ['model' => 'editAbilities', 'groups' => $abilityGroups])
                        @error('editAbilities') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-3">
                        <flux:button variant="ghost" wire:click="$set('showEditModal', false)">Cancel</flux:button>
                        <flux:button variant="primary" type="submit">Save Changes</flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>

        {{-- Revocation Confirmation --}}
        @if($confirmingRevocation)
            <flux:modal wire:model.self="confirmingRevocation">
                <div class="space-y-4">
                    <flux:heading size="lg">Revoke Token</flux:heading>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        Are you sure you want to revoke this token? Any applications using this token will no longer be able to access the API.
                    </p>
                    <div class="flex justify-end gap-3">
                        <flux:button variant="ghost" wire:click="$set('confirmingRevocation', null)">Cancel</flux:button>
                        <flux:button variant="danger" wire:click="revokeToken({{ $confirmingRevocation }})">Revoke Token</flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif
    </x-admin.layout>
</section>
