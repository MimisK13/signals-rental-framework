<x-signals.form-section title="Two-Factor Authentication">
    <x-slot:headerActions>
        @if (Auth::user()->hasTwoFactorEnabled())
            <span class="s-badge s-badge--success">{{ __('Enabled') }}</span>
        @else
            <span class="s-badge s-badge--neutral">{{ __('Disabled') }}</span>
        @endif
    </x-slot:headerActions>

    {{-- Idle state --}}
    @if ($step === 'idle')
        <p class="text-sm text-[var(--text-secondary)] mb-4">
            {{ __('Add an extra layer of security to your account by requiring an authentication code from your phone in addition to your password.') }}
        </p>

        @if (Auth::user()->hasTwoFactorEnabled())
            <div class="flex flex-wrap gap-2">
                <flux:button wire:click="showRecoveryCodes" size="sm">
                    {{ __('View Recovery Codes') }}
                </flux:button>
                <flux:button wire:click="startDisable" variant="danger" size="sm">
                    {{ __('Disable') }}
                </flux:button>
            </div>
        @else
            <flux:button wire:click="startEnable" size="sm">
                {{ __('Enable') }}
            </flux:button>
        @endif
    @endif

    {{-- Password confirmation before enabling --}}
    @if ($step === 'confirming_enable')
        <form wire:submit="enableTwoFactor" class="space-y-4">
            <p class="text-sm text-[var(--text-secondary)]">
                {{ __('Confirm your password to begin setting up two-factor authentication.') }}
            </p>
            <flux:input
                wire:model="password"
                label="{{ __('Current Password') }}"
                type="password"
                name="password"
                required
                autocomplete="current-password"
            />
            <div class="flex gap-2">
                <flux:button type="submit" variant="primary" size="sm">{{ __('Continue') }}</flux:button>
                <flux:button wire:click="cancel" type="button" size="sm">{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    @endif

    {{-- QR code + confirmation --}}
    @if ($step === 'showing_qr')
        <div class="space-y-4">
            <p class="text-sm text-[var(--text-secondary)]">
                {{ __('Scan the QR code below with your authenticator app (e.g. Google Authenticator, Authy), then enter the 6-digit code to confirm setup.') }}
            </p>

            <div class="inline-block rounded-lg border border-[var(--border)] bg-white p-3">
                {!! $qrSvg !!}
            </div>

            <div>
                <p class="text-xs font-medium text-[var(--text-secondary)] mb-1">{{ __('Setup key (manual entry)') }}</p>
                <code class="text-xs font-mono bg-[var(--bg-subtle)] px-2 py-1 rounded">{{ $secret }}</code>
            </div>

            <form
                wire:submit="confirmTwoFactor"
                class="space-y-3"
                x-data="{ submitting: false }"
                x-init="$wire.$interceptMessage('confirmTwoFactor', ({ onFinish }) => onFinish(() => submitting = false))"
            >
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-[var(--text-primary)]">{{ __('Verification code') }}</label>
                    <input
                        wire:model="code"
                        type="text"
                        name="one_time_code"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        pattern="[0-9]*"
                        maxlength="6"
                        class="s-input"
                        required
                        placeholder="123456"
                        x-on:input="
                            const v = $el.value.replace(/\D/g,'').slice(0,6);
                            if (v !== $el.value) $el.value = v;
                            $wire.code = v;
                            if (v.length < 6) submitting = false;
                            if (v.length === 6 && !submitting) {
                                submitting = true;
                                $wire.confirmTwoFactor();
                            }
                        "
                    >
                    @error('code')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex gap-2">
                    <flux:button type="submit" variant="primary" size="sm">{{ __('Confirm') }}</flux:button>
                    <flux:button wire:click="cancel" type="button" size="sm">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </div>
    @endif

    {{-- Recovery codes display --}}
    @if ($step === 'showing_recovery')
        <div class="space-y-4">
            <x-signals.alert>
                {{ __('Save these recovery codes in a secure location. Each code can only be used once. If you lose access to your authenticator app, these codes are the only way to regain access to your account.') }}
            </x-signals.alert>

            <div class="grid grid-cols-2 gap-2 font-mono text-sm bg-[var(--bg-subtle)] rounded-lg p-4">
                @foreach ($recoveryCodes as $recoveryCode)
                    <span>{{ $recoveryCode }}</span>
                @endforeach
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button wire:click="regenerateRecoveryCodes" size="sm">
                    {{ __('Regenerate Codes') }}
                </flux:button>
                <flux:button wire:click="cancel" size="sm" variant="filled">
                    {{ __('Done') }}
                </flux:button>
            </div>
        </div>
    @endif

    {{-- Disable confirmation --}}
    @if ($step === 'confirming_disable')
        <form wire:submit="disableTwoFactor" class="space-y-4">
            <x-signals.alert type="warning">
                {{ __('Disabling two-factor authentication will reduce the security of your account.') }}
            </x-signals.alert>
            <flux:input
                wire:model="password"
                label="{{ __('Confirm your password to disable 2FA') }}"
                type="password"
                name="password"
                required
                autocomplete="current-password"
            />
            <div class="flex gap-2">
                <flux:button type="submit" variant="danger" size="sm">{{ __('Disable 2FA') }}</flux:button>
                <flux:button wire:click="cancel" type="button" size="sm">{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    @endif
</x-signals.form-section>
