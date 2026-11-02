<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Presentation\Livewire;

use App\Domain\Invoices\Models\Invoice;
use App\Services\Notifications\CommunicationLogQuery;
use App\Services\Notifications\CommunicationLogStatusRegistry;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
final class Communications extends Component
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

    public function render(
        CommunicationLogQuery $queryBuilder,
        CommunicationLogStatusRegistry $statusRegistry,
    ): ViewContract {
        return view('invoices::invoices.communications', $this->with($queryBuilder, $statusRegistry));
    }
}
