<div class="flex flex-col gap-6">
    <x-auth::auth-header
        title="Accept invitation"
        :description="'Welcome, ' . $user->name . ' — set a password to finish creating your account.'"
    />

    <form wire:submit="accept" class="flex flex-col gap-5">
        <div class="s-field !mb-0">
            <label class="s-field-label">{{ __('Email address') }}</label>
            <input type="email" name="email" class="s-input" value="{{ $user->email }}" autocomplete="username" readonly>
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

        <button type="submit" class="s-btn s-btn-primary s-btn-block">{{ __('Set password & continue') }}</button>
    </form>
</div>
