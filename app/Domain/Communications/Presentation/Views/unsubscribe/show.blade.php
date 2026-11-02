<x-layouts.auth>
    <div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6 py-12">
        <x-dense::card>
            @if (! empty($unsubscribed))
                <h1 class="text-lg font-semibold text-[var(--text-primary)]" style="font-family: var(--font-display);">
                    {{ __('You have been unsubscribed') }}
                </h1>
                <p class="mt-2 text-sm text-[var(--text-secondary)]">
                    {{ __('You will no longer receive this type of email from us.') }}
                </p>
            @else
                <h1 class="text-lg font-semibold text-[var(--text-primary)]" style="font-family: var(--font-display);">
                    {{ __('Unsubscribe from emails') }}
                </h1>
                <p class="mt-2 text-sm text-[var(--text-secondary)]">
                    @if ($maskedEmail)
                        {{ __('Confirm that you want to stop receiving this type of email at :email.', ['email' => $maskedEmail]) }}
                    @else
                        {{ __('Confirm that you want to stop receiving this type of email.') }}
                    @endif
                </p>

                <form method="POST" action="{{ $confirmUrl }}" class="mt-6">
                    @csrf
                    <button type="submit" class="s-btn s-btn-primary w-full">
                        {{ __('Unsubscribe') }}
                    </button>
                </form>
            @endif
        </x-dense::card>
    </div>
</x-layouts.auth>
