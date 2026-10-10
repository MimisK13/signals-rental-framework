<x-layouts.auth>
    <div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6 py-12">
        <x-dense::card>
            <h1 class="text-lg font-semibold text-[var(--text-primary)]" style="font-family: var(--font-display);">
                {{ __('Enter PIN') }}
            </h1>
            <p class="mt-2 text-sm text-[var(--text-secondary)]">
                {{ __('This document is protected. Enter the PIN provided by the sender.') }}
            </p>

            @if(! empty($error))
                <x-signals.alert type="danger" class="mt-4">{{ $error }}</x-signals.alert>
            @endif

            <form method="POST" action="{{ route('documents.public.verify-pin', ['token' => $token]) }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="pin" class="s-label">{{ __('PIN') }}</label>
                    <input
                        id="pin"
                        name="pin"
                        type="password"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        required
                        class="s-input mt-1 w-full"
                    />
                </div>
                <button type="submit" class="s-btn s-btn-primary w-full">
                    {{ __('View document') }}
                </button>
            </form>
        </x-dense::card>
    </div>
</x-layouts.auth>
