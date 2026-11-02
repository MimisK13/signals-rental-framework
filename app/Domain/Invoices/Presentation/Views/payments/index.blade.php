<section class="w-full">
    <x-dense::page-header title="Payments">
        <x-slot:meta>
            <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">Finance</span>
        </x-slot:meta>
    </x-dense::page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <livewire:components.data-table
            :columns="$columns"
            :model="\App\Domain\Invoices\Models\InvoicePayment::class"
            :searchable="['reference']"
            :with="['invoice.member']"
            :scopes="$scopes"
            default-sort="-paid_at"
            empty-message="No payments found."
            toolbar-view="invoices::payments.partials.toolbar"
            entity-type="invoice_payments"
        />
    </div>
</section>
