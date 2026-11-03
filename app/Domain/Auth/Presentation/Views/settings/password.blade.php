<section class="w-full min-w-0">
    <x-settings.layout heading="Update password" subheading="Ensure your account is using a long, random password to stay secure">
        <x-signals.form-section title="Update password">
            <form wire:submit="updatePassword" class="space-y-6">
                <flux:input
                    wire:model="current_password"
                    label="{{ __('Current password') }}"
                    type="password"
                    name="current_password"
                    required
                    autocomplete="current-password"
                />
                <flux:input
                    wire:model="password"
                    label="{{ __('New password') }}"
                    type="password"
                    name="password"
                    required
                    autocomplete="new-password"
                />
                <flux:input
                    wire:model="password_confirmation"
                    label="{{ __('Confirm Password') }}"
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>

                    <x-action-message on="password-updated">
                        {{ __('Saved.') }}
                    </x-action-message>
                </div>
            </form>
        </x-signals.form-section>
    </x-settings.layout>
</section>
