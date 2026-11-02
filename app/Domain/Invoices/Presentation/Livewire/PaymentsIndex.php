<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Presentation\Livewire;

use App\Domain\Invoices\Enums\PaymentMethod;
use App\Domain\Invoices\Presentation\Tables\InvoicePaymentColumnRegistry;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Payments')]
final class PaymentsIndex extends Component
{
    public function mount(): void
    {
        Gate::authorize('invoices.view');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $registry = app(InvoicePaymentColumnRegistry::class);
        $columnMeta = fn (string $key): array => [
            'sortable' => $registry->get($key)->sortable,
            'filterable' => $registry->get($key)->filterable,
        ];

        $methodOptions = collect(PaymentMethod::cases())
            ->mapWithKeys(fn (PaymentMethod $method): array => [$method->value => $method->label()])
            ->all();

        return [
            'columns' => [
                ['key' => 'reference', 'label' => 'Reference', ...$columnMeta('reference'), 'filter_type' => 'text', 'view' => 'invoices::payments.partials.column-reference'],
                ['key' => 'invoice_id', 'label' => 'Invoice', ...$columnMeta('invoice_id'), 'view' => 'invoices::payments.partials.column-invoice'],
                ['key' => 'member', 'label' => 'Member', 'sortable' => false, 'filterable' => false, 'view' => 'invoices::payments.partials.column-member'],
                ['key' => 'payment_method', 'label' => 'Method', ...$columnMeta('payment_method'), 'filter_type' => 'select', 'filter_options' => $methodOptions, 'view' => 'invoices::payments.partials.column-method'],
                ['key' => 'paid_at', 'label' => 'Paid', ...$columnMeta('paid_at'), 'view' => 'invoices::payments.partials.column-paid-at'],
                ['key' => 'amount', 'label' => 'Amount', ...$columnMeta('amount'), 'view' => 'invoices::payments.partials.column-amount'],
                ['key' => 'is_refund', 'label' => 'Type', 'sortable' => false, 'filterable' => false, 'view' => 'invoices::payments.partials.column-type'],
            ],
            'scopes' => ['forAccessibleStores' => true],
        ];
    }

    public function render(): ViewContract
    {
        return view('invoices::payments.index', $this->with());
    }
}
