<section class="w-full">
    <x-dense::page-header :title="$invoice->number">
        <x-slot:breadcrumbs>
            <a href="{{ route('invoices.index') }}" wire:navigate.hover class="text-[var(--link)] hover:underline">Invoices</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>{{ $invoice->number }}</span>
        </x-slot:breadcrumbs>
        @if($invoice->member)
            <x-slot:meta>
                <a href="{{ route('members.show', $invoice->member) }}" wire:navigate.hover class="s-chip">{{ $invoice->member->name }}</a>
            </x-slot:meta>
        @endif
    </x-dense::page-header>

    @include('invoices::invoices.partials.invoice-tabs', ['invoice' => $invoice, 'activeTab' => 'communications'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        @include('communications::partials.communication-log-table', [
            'logs' => $logs,
            'statuses' => $statuses,
        ])

        <div class="mt-4">{{ $logs->links() }}</div>
    </div>
</section>
