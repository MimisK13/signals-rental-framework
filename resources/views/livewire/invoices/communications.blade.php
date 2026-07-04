<?php

use App\Models\Invoice;
use App\Services\Notifications\CommunicationLogQuery;
use App\Services\Notifications\CommunicationLogStatusRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component
{
    use WithPagination;

    public Invoice $invoice;

    public function mount(Invoice $invoice): void
    {
        Gate::authorize('view', $invoice);

        $this->invoice = $invoice->load(['member', 'store']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->invoice->number.' — Communications');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(
        CommunicationLogQuery $queryBuilder,
        CommunicationLogStatusRegistry $statusRegistry,
    ): array {
        $user = auth()->user();

        $logs = $queryBuilder
            ->forInvoice($this->invoice, $user)
            ->with(['notifiable'])
            ->latest('created_at')
            ->paginate(25);

        return [
            'logs' => $logs,
            'statuses' => $statusRegistry->statuses(),
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header :title="$invoice->number">
        <x-slot:breadcrumbs>
            <a href="{{ route('invoices.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Invoices</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>{{ $invoice->number }}</span>
        </x-slot:breadcrumbs>
        @if($invoice->member)
            <x-slot:meta>
                <a href="{{ route('members.show', $invoice->member) }}" wire:navigate class="s-chip">{{ $invoice->member->name }}</a>
            </x-slot:meta>
        @endif
    </x-signals.page-header>

    @include('livewire.invoices.partials.invoice-tabs', ['invoice' => $invoice, 'activeTab' => 'communications'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        @include('livewire.notifications.partials.communication-log-table', [
            'logs' => $logs,
            'statuses' => $statuses,
        ])

        <div class="mt-4">{{ $logs->links() }}</div>
    </div>
</section>
