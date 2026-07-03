<?php

namespace App\Data\Invoices;

use App\Enums\InvoiceType;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class GenerateInvoiceFromOpportunityData extends Data
{
    public function __construct(
        public int $opportunity_id,
        public string $type = InvoiceType::Standard->value,
        public ?string $notes = null,
        public ?string $reference = null,
        /** @var array<int, string>|null */
        public ?array $tag_list = null,
        /**
         * Optional per-line partial quantities. Each entry selects an opportunity
         * item and the quantity to invoice on this checkout. Omit to invoice all
         * remaining quantities.
         *
         * @var list<array{opportunity_item_id: int, quantity: string}>|null
         */
        public ?array $lines = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(ValidationContext $context): array
    {
        return [
            'opportunity_id' => ['required', 'integer', 'exists:opportunities,id'],
            'type' => ['required', 'string', Rule::in(array_column(InvoiceType::cases(), 'value'))],
            'notes' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:255'],
            'tag_list' => ['nullable', 'array'],
            'tag_list.*' => ['string', 'max:100'],
            'lines' => ['nullable', 'array'],
            'lines.*.opportunity_item_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
