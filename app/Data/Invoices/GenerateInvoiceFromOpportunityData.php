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
        ];
    }
}
