<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Services;

use App\Domain\Documents\Contracts\DocumentDataResolver;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationItem;
use App\Models\Member;
use App\Models\Store;
use App\Services\FieldBuilder;
use App\Services\SchemaBuilder;
use Brick\Money\Currency;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

final class QuotationDataResolver implements DocumentDataResolver
{
    public function type(): string
    {
        return 'quotation';
    }

    public function label(): string
    {
        return 'Quotation';
    }

    public function description(): string
    {
        return 'Independent and project quotations with customer, line items, and totals.';
    }

    public function registerFields(SchemaBuilder $builder): void
    {
        $this->field($builder->string('quotation.number'))->label('Number')->group('Quotation');
        $this->field($builder->string('quotation.subject'))->label('Subject')->group('Quotation');
        $this->field($builder->string('quotation.reference'))->label('Reference')->group('Quotation');
        $this->field($builder->string('quotation.status'))->label('Status')->group('Quotation');
        $this->field($builder->date('quotation.starts_at'))->label('Start Date')->group('Quotation');
        $this->field($builder->date('quotation.ends_at'))->label('End Date')->group('Quotation');
        $this->field($builder->date('quotation.valid_until'))->label('Valid Until')->group('Quotation');
        $this->field($builder->currency('quotation.net_total'))->label('Net Total')->group('Quotation');
        $this->field($builder->currency('quotation.tax_total'))->label('Tax Total')->group('Quotation');
        $this->field($builder->currency('quotation.total'))->label('Total')->group('Quotation');
        $this->field($builder->string('member.name'))->label('Customer')->group('Customer');
        $this->field($builder->string('member.email'))->label('Email')->group('Customer');
        $this->field($builder->string('member.phone'))->label('Phone')->group('Customer');
        $this->field($builder->string('store.name'))->label('Store')->group('Store');
        $this->field($builder->collection('items'))->label('Line Items (loop)')->group('Line Items');
        $this->field($builder->string('item.name'))->label('Name')->group('Line Items');
        $this->field($builder->decimal('item.quantity'))->label('Quantity')->group('Line Items');
        $this->field($builder->currency('item.unit_price'))->label('Unit Price')->group('Line Items');
        $this->field($builder->currency('item.total'))->label('Net Total')->group('Line Items');
        $this->field($builder->currency('item.tax_total'))->label('Tax')->group('Line Items');
        $this->field($builder->currency('item.gross_total'))->label('Gross Total')->group('Line Items');
        $this->field($builder->string('item.description'))->label('Description')->group('Line Items');
    }

    public function loadSource(int $sourceId): Model
    {
        return Quotation::query()
            ->with([
                'store',
                'activeVersion.items' => fn (Relation $query) => $query->orderBy('path')->orderBy('id'),
                'customer.emails' => fn (Relation $query) => $query->where('is_primary', true),
                'customer.phones' => fn (Relation $query) => $query->where('is_primary', true),
            ])
            ->findOrFail($sourceId);
    }

    public function resolve(Model $source): array
    {
        if (! $source instanceof Quotation) {
            throw new \InvalidArgumentException('QuotationDataResolver expects a Quotation model.');
        }

        $currency = $source->currency_code;

        return [
            'quotation' => [
                'number' => $source->number,
                'subject' => $source->subject,
                'reference' => $source->reference,
                'description' => $source->description,
                'status' => $source->status->value,
                'starts_at' => $source->starts_at?->toDateString(),
                'ends_at' => $source->ends_at?->toDateString(),
                'valid_until' => $source->valid_until?->toDateString(),
                'net_total' => $this->money($source->charge_excluding_tax_total, $currency),
                'tax_total' => $this->money($source->tax_total, $currency),
                'total' => $this->money($source->charge_including_tax_total, $currency),
            ],
            'member' => $this->member($source->customer),
            'store' => $this->store($source->store),
            'items' => $source->activeVersion?->items
                ->map(fn (QuotationItem $item): array => [
                    'name' => $item->name,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $this->money($item->unit_price, $currency),
                    'total' => $this->money($item->total, $currency),
                    'tax_total' => $this->money($item->tax_total, $currency),
                    'gross_total' => $this->money($item->gross_total, $currency),
                    'optional' => $item->is_optional,
                ])->all() ?? [],
        ];
    }

    private function field(FieldBuilder $field): FieldBuilder
    {
        return $field->source('document')->filterable(false)->sortable(false)->exportable(true);
    }

    /** @return array{name: string|null, email: string|null, phone: string|null, vat_number: string|null} */
    private function member(?Member $member): array
    {
        return [
            'name' => $member?->name,
            'email' => $member?->emails->first()?->address,
            'phone' => $member?->phones->first()?->number,
            'vat_number' => $member?->tax_number,
        ];
    }

    /** @return array{name: string|null} */
    private function store(?Store $store): array
    {
        return ['name' => $store?->name];
    }

    private function money(int $minor, string $currency): string
    {
        return Money::ofMinor($minor, Currency::of($currency))->getAmount()->__toString();
    }
}
