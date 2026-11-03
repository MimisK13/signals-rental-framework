<div class="flex flex-col gap-6">
    <x-auth::auth-header title="Reset password" description="Choose a new password for your account." />

    @if (session('status'))
        <x-signals.alert type="success">{{ session('status') }}</x-signals.alert>
    @endif

    <form wire:submit="resetPassword" class="flex flex-col gap-5">
        <div class="s-field !mb-0 {{ $errors->has('email') ? 'has-error' : '' }}">
            <label class="s-field-label">{{ __('Email') }}</label>
            <input wire:model="email" type="email" name="email" class="s-input" required autocomplete="email">
            @error('email') <div class="s-field-error">{{ $message }}</div> @enderror
        </div>

        <div class="s-field !mb-0 {{ $errors->has('password') ? 'has-error' : '' }}">
            <label class="s-field-label">{{ __('Password') }}</label>
            <input wire:model="password" type="password" name="password" class="s-input" required autocomplete="new-password" placeholder="Password">
            @error('password') <div class="s-field-error">{{ $message }}</div> @enderror
        </div>

        <div class="s-field !mb-0 {{ $errors->has('password_confirmation') ? 'has-error' : '' }}">
            <label class="s-field-label">{{ __('Confirm password') }}</label>
            <input wire:model="password_confirmation" type="password" name="password_confirmation" class="s-input" required autocomplete="new-password" placeholder="Confirm password">
            @error('password_confirmation') <div class="s-field-error">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="s-btn s-btn-primary s-btn-block">{{ __('Reset password') }}</button>
    </form>
</div>
