<div class="flex flex-col gap-6">
    <x-auth-header
        title="Two-factor authentication"
        description="{{ $useRecovery ? __('Enter one of your recovery codes to continue.') : __('Enter the authentication code from your app to continue.') }}"
    />

    {{--
        The `submitting` flag is a UX double-submit guard ONLY: it prevents the
        6-digit auto-submit from firing the same request twice while one is already
        in flight. It is NOT a security control. Brute-force protection is enforced
        server-side by the RateLimiter in authenticate() (5 attempts per user+IP),
        which cannot be bypassed by tampering with this client-side flag.
    --}}
    <form
        wire:submit="authenticate"
        class="flex flex-col gap-5"
        x-data="{ submitting: false }"
        x-init="$wire.$interceptMessage('authenticate', ({ onFinish }) => onFinish(() => submitting = false))"
    >
        @if (! $useRecovery)
            <div class="s-field !mb-0 {{ $errors->has('code') ? 'has-error' : '' }}">
                <label class="s-field-label">{{ __('Authentication code') }}</label>
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
                    autofocus
                    placeholder="123456"
                    x-on:input="
                        const v = $el.value.replace(/\D/g,'').slice(0,6);
                        if (v !== $el.value) $el.value = v;
                        $wire.code = v;
                        if (v.length < 6) submitting = false;
                        if (v.length === 6 && !submitting) {
                            submitting = true;
                            $wire.authenticate();
                        }
                    "
                >
                @error('code') <div class="s-field-error">{{ $message }}</div> @enderror
            </div>
        @else
            <div class="s-field !mb-0 {{ $errors->has('recoveryCode') ? 'has-error' : '' }}">
                <label class="s-field-label">{{ __('Recovery code') }}</label>
                <input wire:model="recoveryCode" type="text" name="recovery_code" class="s-input" required autocomplete="one-time-code" placeholder="XXXX-XXXX">
                @error('recoveryCode') <div class="s-field-error">{{ $message }}</div> @enderror
            </div>
        @endif

        <button type="submit" class="s-btn s-btn-primary s-btn-block">{{ __('Verify') }}</button>
    </form>

    <div class="text-center">
        <button wire:click="toggleRecovery" type="button" class="s-btn s-btn-ghost s-btn-sm">
            {{ $useRecovery ? __('Use an authentication app instead') : __('Use a recovery code instead') }}
        </button>
    </div>
</div>
