<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Presentation\Livewire;

use App\Domain\Invoices\Models\CreditNote;
use App\Domain\Invoices\Presentation\Tables\CreditNoteColumnRegistry;
use App\Domain\Invoices\Services\CreditNoteStatusRegistry;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Credit Notes')]
final class CreditNotesIndex extends Component
{
    #[Url(as: 'status')]
    public string $statusFilter = '';

    /** @var Collection<string, int> */
    public Collection $statusCounts;

    public int $totalCount = 0;

    public function mount(): void
    {
        Gate::authorize('invoices.view');

        $this->refreshStatusCounts();
    }

    public function setStatusFilter(string $status): void
    {
        if ($status !== '' && ! array_key_exists($status, config('credit_notes.statuses', []))) {
            return;
        }

        $this->statusFilter = $status;
    }

    public function refreshStatusCounts(): void
    {
        $this->statusCounts = CreditNote::query()
            ->forAccessibleStores()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $this->totalCount = (int) $this->statusCounts->sum();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $registry = app(CreditNoteColumnRegistry::class);
        $columnMeta = fn (string $key): array => [
            'sortable' => $registry->get($key)->sortable,
            'filterable' => $registry->get($key)->filterable,
        ];

        /** @var array<string, array{label?: string}> $statusConfig */
        $statusConfig = config('credit_notes.statuses', []);
        $statusOptions = collect($statusConfig)
            ->mapWithKeys(fn (array $meta, string $key): array => [$key => $meta['label'] ?? $key])
            ->all();

        $scopes = ['forAccessibleStores' => true];

        if ($this->statusFilter !== '') {
            $scopes['ofStatus'] = $this->statusFilter;
        }

        return [
            'statuses' => app(CreditNoteStatusRegistry::class)->statuses(),
            'statusCounts' => $this->statusCounts,
            'totalCount' => $this->totalCount,
            'columns' => [
                ['key' => 'number', 'label' => 'Number', ...$columnMeta('number'), 'filter_type' => 'text', 'view' => 'invoices::credit-notes.partials.column-number'],
                ['key' => 'invoice_id', 'label' => 'Invoice', ...$columnMeta('invoice_id'), 'view' => 'invoices::credit-notes.partials.column-invoice'],
                ['key' => 'member', 'label' => 'Member', 'sortable' => false, 'filterable' => false, 'view' => 'invoices::credit-notes.partials.column-member'],
                ['key' => 'issued_at', 'label' => 'Issued', ...$columnMeta('issued_at'), 'view' => 'invoices::credit-notes.partials.column-issued'],
                ['key' => 'total', 'label' => 'Total', ...$columnMeta('total'), 'view' => 'invoices::credit-notes.partials.column-total'],
                ['key' => 'status', 'label' => 'Status', ...$columnMeta('status'), 'filter_type' => 'select', 'filter_options' => $statusOptions, 'view' => 'invoices::credit-notes.partials.column-status'],
            ],
            'scopes' => $scopes,
        ];
    }

    public function render(): ViewContract
    {
        return view('invoices::credit-notes.index', $this->with());
    }
}
