{{--
    Global confirmation modal for wire:confirm actions. Shown imperatively via
    window.signals.confirm.show() — see resources/js/livewire-confirm.js.
--}}
<div x-data="signalsConfirmModal()" x-cloak>
    <flux:modal
        name="signals-confirm"
        class="max-w-md"
        data-testid="signals-confirm-modal"
        x-on:cancel="cancel()"
        x-on:close="cancel()"
    >
        <form x-on:submit.prevent="confirm()" class="space-y-6">
            <flux:heading size="lg">{{ __('Confirm') }}</flux:heading>

            <flux:text class="mt-2 whitespace-pre-line" x-text="message"></flux:text>

            <div class="flex justify-end gap-2">
                <button type="button" class="s-btn s-btn-ghost" x-on:click="cancel()">
                    {{ __('Cancel') }}
                </button>
                <button
                    type="submit"
                    x-ref="confirmButton"
                    x-bind:class="danger ? 's-btn s-btn-danger' : 's-btn s-btn-accent'"
                    data-testid="signals-confirm-submit"
                >
                    {{ __('Confirm') }}
                </button>
            </div>
        </form>
    </flux:modal>
</div>
