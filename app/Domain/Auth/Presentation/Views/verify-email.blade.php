<div class="flex flex-col gap-6">
    <x-auth-header
        title="Verify your email"
        description="Click the verification link we've just emailed you."
    />

    @if (session('status') == 'verification-link-sent')
        <x-signals.alert type="success">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </x-signals.alert>
    @endif

    <div class="flex flex-col gap-3">
        <button wire:click="sendVerification" type="button" class="s-btn s-btn-primary s-btn-block">{{ __('Resend verification email') }}</button>
        <button wire:click="logout" type="button" class="s-btn s-btn-ghost s-btn-block">{{ __('Log out') }}</button>
    </div>
</div>
