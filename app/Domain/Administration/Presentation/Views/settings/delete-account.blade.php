<section class="mt-6 space-y-6">
    <x-signals.form-section title="Delete Account">
        <p class="text-sm text-[var(--text-secondary)]">
            {{ __('Permanently delete your account and all of its resources.') }}
        </p>

        @php($selfDeleteBlockReason = $this->selfDeleteBlockReason())

        @if($selfDeleteBlockReason)
            <p class="mt-4 text-sm text-[var(--red)]">
                {{ $selfDeleteBlockReason }}
            </p>
        @else
            <div class="mt-4">
                <flux:button
                    variant="danger"
                    size="sm"
                    x-data
                    x-on:click="$dispatch('open-modal', 'confirm-user-deletion')"
                >
                    {{ __('Delete Account') }}
                </flux:button>
            </div>
        @endif
    </x-signals.form-section>

    <x-signals.modal name="confirm-user-deletion" title="{{ __('Delete Account') }}">
        <form wire:submit="deleteUser" class="space-y-4">
            <p class="text-sm text-[var(--text-secondary)]">
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm.') }}
            </p>

            <flux:input wire:model="password" label="{{ __('Password') }}" type="password" name="password" />

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <flux:button
                        type="button"
                        variant="filled"
                        x-data
                        x-on:click="$dispatch('close-modal', 'confirm-user-deletion')"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button variant="danger" type="submit">{{ __('Delete Account') }}</flux:button>
                </div>
            </x-slot:footer>
        </form>
    </x-signals.modal>
</section>
