<?php

use App\Enums\PaymentMethod;
use App\Models\InvoicePayment;
use App\Views\InvoicePaymentColumnRegistry;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Payments')] class extends Component {
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
            'sortable' => $registry->get($key)?->sortable ?? false,
            'filterable' => $registry->get($key)?->filterable ?? false,
        ];

        $methodOptions = collect(PaymentMethod::cases())
            ->mapWithKeys(fn (PaymentMethod $method): array => [$method->value => $method->label()])
            ->all();

        return [
            'columns' => [
                ['key' => 'reference', 'label' => 'Reference', ...$columnMeta('reference'), 'filter_type' => 'text', 'view' => 'livewire.payments.partials.column-reference'],
                ['key' => 'invoice_id', 'label' => 'Invoice', ...$columnMeta('invoice_id'), 'view' => 'livewire.payments.partials.column-invoice'],
                ['key' => 'member', 'label' => 'Member', 'sortable' => false, 'filterable' => false, 'view' => 'livewire.payments.partials.column-member'],
                ['key' => 'payment_method', 'label' => 'Method', ...$columnMeta('payment_method'), 'filter_type' => 'select', 'filter_options' => $methodOptions, 'view' => 'livewire.payments.partials.column-method'],
                ['key' => 'paid_at', 'label' => 'Paid', ...$columnMeta('paid_at'), 'view' => 'livewire.payments.partials.column-paid-at'],
                ['key' => 'amount', 'label' => 'Amount', ...$columnMeta('amount'), 'view' => 'livewire.payments.partials.column-amount'],
                ['key' => 'is_refund', 'label' => 'Type', 'sortable' => false, 'filterable' => false, 'view' => 'livewire.payments.partials.column-type'],
            ],
            'scopes' => ['forAccessibleStores' => true],
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header title="Payments">
        <x-slot:meta>
            <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">Finance</span>
        </x-slot:meta>
    </x-signals.page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <livewire:components.data-table
            :columns="$columns"
            :model="\App\Models\InvoicePayment::class"
            :searchable="['reference']"
            :with="['invoice.member']"
            :scopes="$scopes"
            default-sort="-paid_at"
            empty-message="No payments found."
            toolbar-view="livewire.payments.partials.toolbar"
            entity-type="invoice_payments"
        />
    </div>
</section>
