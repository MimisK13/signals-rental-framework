<section class="w-full min-w-0">
    <x-settings.layout heading="Profile" subheading="Update your name and email address">
        @if (session('status') && session('status') !== 'verification-link-sent')
            <x-signals.alert type="success" dismissible class="mb-6">{{ session('status') }}</x-signals.alert>
        @endif

        <x-signals.form-section title="Profile photo">
            <p class="mb-4 text-sm text-[var(--text-secondary)]">{{ __('Upload a photo to personalise your account.') }}</p>
            <livewire:components.icon-upload :model="$member" :key="'profile-photo-'.$member->id" />
        </x-signals.form-section>

        <div class="mt-8"></div>

        <x-signals.form-section title="Profile information">
            <form wire:submit="updateProfileInformation" class="space-y-6">
                <flux:input wire:model="name" label="{{ __('Name') }}" type="text" name="name" required autofocus autocomplete="name" />

                <div>
                    <flux:input wire:model="email" label="{{ __('Email') }}" type="email" name="email" required autocomplete="email" />

                    @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                        <div class="mt-2 space-y-1">
                            <p class="text-sm text-[var(--text-secondary)]">
                                {{ __('Your email address is unverified.') }}

                                <button
                                    wire:click.prevent="resendVerificationNotification"
                                    class="underline hover:text-[var(--text-primary)]"
                                >
                                    {{ __('Click here to re-send the verification email.') }}
                                </button>
                            </p>

                            @if (session('status') === 'verification-link-sent')
                                <p class="text-sm font-medium text-green-600">
                                    {{ __('A new verification link has been sent to your email address.') }}
                                </p>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </x-signals.form-section>

        <div class="mt-8">
            <livewire:settings.two-factor-authentication-form />
        </div>

        <livewire:settings.delete-account />
    </x-settings.layout>
</section>
