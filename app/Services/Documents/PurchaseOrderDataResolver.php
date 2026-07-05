<?php

namespace App\Services\Documents;

use App\Contracts\Documents\DocumentDataResolver;
use App\Enums\MembershipType;
use App\Models\Member;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Store;
use App\Services\FieldBuilder;
use App\Services\SchemaBuilder;
use App\Support\Timezone;
use Brick\Money\Currency;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * Purchase order documents render against the event-sourced PurchaseOrder projection.
 */
class PurchaseOrderDataResolver implements DocumentDataResolver
{
    public function __construct(
        private readonly Timezone $timezone,
        private readonly DocumentRenderContext $renderContext,
    ) {}

    public function type(): string
    {
        return 'purchase_order';
    }

    public function label(): string
    {
        return 'Purchase Order';
    }

    public function description(): string
    {
        return 'Supplier purchase order with line items, totals, and delivery window.';
    }

    public function registerFields(SchemaBuilder $builder): void
    {
        $this->documentField($builder->string('purchase_order.number'))->label('PO Number')->group('Purchase Order');
        $this->documentField($builder->string('purchase_order.po_type_label'))->label('Type')->group('Purchase Order');
        $this->documentField($builder->string('purchase_order.status_label'))->label('Status')->group('Purchase Order');
        $this->documentField($builder->date('purchase_order.expected_at'))->label('Expected Date')->group('Purchase Order');
        $this->documentField($builder->date('purchase_order.starts_at'))->label('Starts')->group('Purchase Order');
        $this->documentField($builder->date('purchase_order.ends_at'))->label('Ends')->group('Purchase Order');
        $this->documentField($builder->string('purchase_order.reference'))->label('Reference')->group('Purchase Order');
        $this->documentField($builder->text('purchase_order.notes'))->label('Notes')->group('Purchase Order');
        $this->documentField($builder->string('purchase_order.supplier_reference'))->label('Supplier Reference')->group('Purchase Order');
        $this->documentField($builder->currency('purchase_order.subtotal'))->label('Subtotal')->group('Purchase Order');
        $this->documentField($builder->currency('purchase_order.tax_total'))->label('Tax Total')->group('Purchase Order');
        $this->documentField($builder->currency('purchase_order.total'))->label('Total')->group('Purchase Order');

        $this->documentField($builder->string('opportunity.number'))->label('Opportunity Number')->group('Opportunity');
        $this->documentField($builder->string('opportunity.subject'))->label('Opportunity Subject')->group('Opportunity');

        $this->documentField($builder->collection('lines'))->label('Line Items (loop)')->group('Line Items');
        $this->documentField($builder->string('line.description'))->label('Description')->group('Line Items');
        $this->documentField($builder->decimal('line.quantity'))->label('Quantity')->group('Line Items');
        $this->documentField($builder->currency('line.unit_cost'))->label('Unit Cost')->group('Line Items');
        $this->documentField($builder->currency('line.tax'))->label('Line Tax')->group('Line Items');
        $this->documentField($builder->currency('line.total'))->label('Line Total')->group('Line Items');

        $this->documentField($builder->string('supplier.name'))->label('Supplier Name')->group('Supplier');
        $this->documentField($builder->string('supplier.company_name'))->label('Supplier Company')->group('Supplier');
        $this->documentField($builder->string('supplier.email'))->label('Email')->group('Supplier');
        $this->documentField($builder->string('supplier.phone'))->label('Phone')->group('Supplier');

        $this->documentField($builder->string('store.name'))->label('Store Name')->group('Store');
        $this->documentField($builder->string('store.address'))->label('Address')->group('Store');
        $this->documentField($builder->string('store.phone'))->label('Phone')->group('Store');
        $this->documentField($builder->string('store.email'))->label('Email')->group('Store');
    }

    private function documentField(FieldBuilder $field): FieldBuilder
    {
        return $field
            ->source('document')
            ->filterable(false)
            ->sortable(false)
            ->exportable(true);
    }

    public function loadSource(int $sourceId): Model
    {
        return PurchaseOrder::query()
            ->with([
                'supplier.emails' => fn ($query) => $query->where('is_primary', true),
                'supplier.phones' => fn ($query) => $query->where('is_primary', true),
                'store',
                'opportunity',
                'items' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'items.product',
            ])
            ->findOrFail($sourceId);
    }

    public function resolve(Model $source): array
    {
        if (! $source instanceof PurchaseOrder) {
            throw new \InvalidArgumentException('PurchaseOrderDataResolver expects a PurchaseOrder model.');
        }

        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = $source;
        $supplier = $purchaseOrder->supplier;
        $store = $this->resolveContextStore($purchaseOrder->store);
        $currencyCode = $purchaseOrder->currency_code ?: (string) settings('company.base_currency', 'GBP');
        $timezone = $store?->timezone ?: 'UTC';

        return [
            'purchase_order' => $this->resolvePurchaseOrder($purchaseOrder, $currencyCode, $timezone),
            'opportunity' => $this->resolveOpportunity($purchaseOrder),
            'lines' => $this->resolveLines($purchaseOrder, $currencyCode),
            'supplier' => $this->resolveSupplier($supplier),
            'store' => $this->resolveStore($store),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePurchaseOrder(PurchaseOrder $purchaseOrder, string $currencyCode, string $timezone): array
    {
        $typeLabel = (string) (config('purchase_orders.types.'.$purchaseOrder->po_type.'.label') ?? $purchaseOrder->po_type);
        $statusLabel = (string) (config('purchase_orders.statuses.'.$purchaseOrder->status.'.label') ?? $purchaseOrder->status);

        return [
            'number' => $purchaseOrder->number,
            'po_type_label' => $typeLabel,
            'status_label' => $statusLabel,
            'expected_at' => $this->formatDate($purchaseOrder->expected_at, $timezone),
            'starts_at' => $this->formatDate($purchaseOrder->starts_at, $timezone),
            'ends_at' => $this->formatDate($purchaseOrder->ends_at, $timezone),
            'reference' => $purchaseOrder->reference,
            'notes' => $purchaseOrder->notes,
            'supplier_reference' => $purchaseOrder->supplier_reference,
            'subtotal' => $this->formatMoney($purchaseOrder->subtotal, $currencyCode),
            'tax_total' => $this->formatMoney($purchaseOrder->tax_total, $currencyCode),
            'total' => $this->formatMoney($purchaseOrder->total, $currencyCode),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function resolveOpportunity(PurchaseOrder $purchaseOrder): array
    {
        $opportunity = $purchaseOrder->opportunity;

        if ($opportunity === null) {
            return [
                'number' => null,
                'subject' => null,
            ];
        }

        return [
            'number' => $opportunity->number,
            'subject' => $opportunity->subject,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveLines(PurchaseOrder $purchaseOrder, string $currencyCode): array
    {
        return $purchaseOrder->items
            ->map(fn (PurchaseOrderItem $line): array => [
                'description' => $line->description,
                'quantity' => (string) $line->quantity,
                'unit_cost' => $this->formatMoney($line->unit_cost, $currencyCode),
                'tax' => $this->formatMoney($line->tax, $currencyCode),
                'total' => $this->formatMoney($line->total, $currencyCode),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveSupplier(?Member $supplier): array
    {
        if ($supplier === null) {
            return [
                'name' => null,
                'company_name' => null,
                'email' => null,
                'phone' => null,
            ];
        }

        return [
            'name' => $supplier->name,
            'company_name' => $supplier->membership_type === MembershipType::Organisation ? $supplier->name : null,
            'email' => $supplier->emails->first()?->address,
            'phone' => $supplier->phones->first()?->number,
        ];
    }

    private function resolveContextStore(?Store $store): ?Store
    {
        if ($this->renderContext->storeId !== null) {
            return Store::query()->find($this->renderContext->storeId) ?? $store;
        }

        return $store;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveStore(?Store $store): array
    {
        if ($store === null) {
            return [
                'name' => null,
                'address' => null,
                'phone' => null,
                'email' => null,
            ];
        }

        return [
            'name' => $store->name,
            'address' => $this->formatStoreAddress($store),
            'phone' => $store->phone,
            'email' => $store->email,
        ];
    }

    private function formatStoreAddress(Store $store): ?string
    {
        $parts = array_values(array_filter([
            $store->street,
            $store->city,
            $store->county,
            $store->postcode,
        ], fn (?string $part): bool => is_string($part) && $part !== ''));

        return $parts === [] ? null : implode(', ', $parts);
    }

    private function formatDate(mixed $value, string $timezone): ?string
    {
        if ($value === null) {
            return null;
        }

        $format = settings('company.date_format_php') ?? 'd/m/Y';

        return $this->timezone->toZone($value, $timezone)->format($format);
    }

    private function formatMoney(int $minorUnits, string $currencyCode): string
    {
        $currency = Currency::of($currencyCode);

        return (string) Money::ofMinor($minorUnits, $currency)
            ->getAmount()
            ->toScale($currency->getDefaultFractionDigits());
    }
}
