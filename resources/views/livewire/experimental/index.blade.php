<?php

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Experimental')] class extends Component
{
    public function mount(): void
    {
        Gate::authorize('opportunities.access');
    }
}; ?>

<section class="w-full">
    <x-dense::page-header title="Experimental">
        <x-slot:meta>
            <span class="s-badge s-badge-amber">Internal workflow evaluation</span>
        </x-slot:meta>
    </x-dense::page-header>

    <div class="w-full px-6 py-4 max-md:px-5 max-sm:px-3">
        <a href="{{ route('experimental.quotations.index') }}" wire:navigate.hover class="block w-full">
            <x-dense::card class="transition hover:border-[var(--brand-primary)]">
                <div class="mb-2 flex items-center gap-2 font-semibold">
                    <flux:icon.document-text class="size-5" />
                    Quotations
                </div>
                <p class="text-sm text-[var(--text-muted)]">Alternative quotation workflow using the canonical Opportunity domain.</p>
            </x-dense::card>
        </a>
    </div>
</section>
