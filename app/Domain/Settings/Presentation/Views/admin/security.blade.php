<section class="w-full min-w-0">
    <x-admin.layout group="users" title="Security" description="Configure password policies, session settings, and two-factor authentication.">
        <form wire:submit="save" class="space-y-8">
            <x-signals.form-section title="Password Policy">
                <div class="space-y-4">
                    <flux:input wire:model="passwordMinLength" label="Minimum Password Length" type="number" min="6" max="128" />

                    <div class="space-y-2">
                        <label class="flex items-center gap-2 cursor-pointer" x-data="{ checked: @js($passwordRequireUppercase) }">
                            <input type="checkbox" wire:model="passwordRequireUppercase" class="hidden" x-on:change="checked = $el.checked" />
                            <x-signals.checkbox x-bind:class="checked && 'checked'" />
                            <span class="text-sm">Require uppercase letter</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer" x-data="{ checked: @js($passwordRequireNumber) }">
                            <input type="checkbox" wire:model="passwordRequireNumber" class="hidden" x-on:change="checked = $el.checked" />
                            <x-signals.checkbox x-bind:class="checked && 'checked'" />
                            <span class="text-sm">Require number</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer" x-data="{ checked: @js($passwordRequireSpecial) }">
                            <input type="checkbox" wire:model="passwordRequireSpecial" class="hidden" x-on:change="checked = $el.checked" />
                            <x-signals.checkbox x-bind:class="checked && 'checked'" />
                            <span class="text-sm">Require special character</span>
                        </label>
                    </div>
                </div>
            </x-signals.form-section>

            <x-signals.form-section title="Login Security">
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="maxLoginAttempts" label="Max Login Attempts" type="number" min="1" max="100" />
                    <flux:input wire:model="lockoutDuration" label="Lockout Duration (minutes)" type="number" min="1" max="1440" />
                </div>
            </x-signals.form-section>

            <x-signals.form-section title="Session">
                <flux:input wire:model="sessionTimeout" label="Session Timeout (minutes)" type="number" min="5" max="1440" />
                <p class="text-xs text-zinc-500 mt-1">Inactive sessions will expire after this duration.</p>
            </x-signals.form-section>

            <x-signals.form-section title="Two-Factor Authentication">
                <div class="space-y-2">
                    <label class="flex items-center gap-2 cursor-pointer" x-data="{ checked: @js($require2faAdmin) }">
                        <input type="checkbox" wire:model="require2faAdmin" class="hidden" x-on:change="checked = $el.checked" />
                        <x-signals.checkbox x-bind:class="checked && 'checked'" />
                        <span class="text-sm">Require 2FA for admin users</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer" x-data="{ checked: @js($require2faAll) }">
                        <input type="checkbox" wire:model="require2faAll" class="hidden" x-on:change="checked = $el.checked" />
                        <x-signals.checkbox x-bind:class="checked && 'checked'" />
                        <span class="text-sm">Require 2FA for all users</span>
                    </label>
                </div>
            </x-signals.form-section>

            <x-signals.form-section title="Passwordless Login">
                <div class="space-y-2">
                    <label class="flex items-center gap-2 cursor-pointer" x-data="{ checked: @js($magicLinkEnabled) }">
                        <input type="checkbox" wire:model="magicLinkEnabled" class="hidden" x-on:change="checked = $el.checked" />
                        <x-signals.checkbox x-bind:class="checked && 'checked'" />
                        <span class="text-sm">Email magic-link login</span>
                    </label>
                    <p class="text-xs text-zinc-500">Allow users to sign in via a one-time link emailed to them; never bypasses two-factor authentication.</p>
                </div>
            </x-signals.form-section>

            <x-signals.form-section title="Single Sign-On Enforcement">
                <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-3">
                    Users in the selected roles must sign in with single sign-on and cannot use password login. The Owner is always exempt as a break-glass account.
                </p>
                @if($enforceableRoles->isEmpty())
                    <p class="text-sm text-zinc-500">No roles available. Create a role before enforcing single sign-on.</p>
                @else
                    <div class="space-y-2">
                        @foreach($enforceableRoles as $role)
                            <label class="flex items-center gap-2 cursor-pointer" wire:key="sso-role-{{ $role->id }}"
                                   x-data="{ checked: @js(in_array($role->name, $ssoEnforcedRoles, true)) }"
                                   x-init="$watch('$wire.ssoEnforcedRoles', v => checked = v.includes('{{ $role->name }}'))">
                                <input type="checkbox" wire:model="ssoEnforcedRoles" value="{{ $role->name }}" class="hidden" x-on:change="checked = $el.checked" />
                                <x-signals.checkbox x-bind:class="checked && 'checked'" />
                                <span class="text-sm">{{ $role->name }}</span>
                                @if($role->description)
                                    <span class="text-xs text-zinc-500">- {{ $role->description }}</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                    @error('ssoEnforcedRoles') <div class="s-field-error">{{ $message }}</div> @enderror
                @endif
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">Save Changes</flux:button>

                <x-action-message on="security-settings-saved">
                    Saved.
                </x-action-message>
            </div>
        </form>
    </x-admin.layout>
</section>
