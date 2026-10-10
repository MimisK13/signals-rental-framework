<x-layouts.auth>
    <div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6 py-12">
        <x-signals.card>
            <h1 class="text-lg font-semibold text-(--text-primary)" style="font-family: var(--font-display);">
                {{ __('Document unavailable') }}
            </h1>
            <p class="mt-3 text-sm text-(--text-secondary)">
                {{ __('This shared document link has expired. Please contact the sender for a new link.') }}
            </p>
        </x-signals.card>
    </div>
</x-layouts.auth>
