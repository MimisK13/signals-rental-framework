<?php

namespace App\Data\Invoices;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class GenerateDepositInvoiceData extends Data
{
    public function __construct(
        public int $opportunity_id,
        public ?string $amount_percentage = null,
        public ?int $amount_fixed = null,
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
            'amount_percentage' => ['nullable', 'numeric', 'gt:0', 'lte:100', 'required_without:amount_fixed'],
            'amount_fixed' => ['nullable', 'integer', 'gt:0', 'required_without:amount_percentage'],
            'notes' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:255'],
            'tag_list' => ['nullable', 'array'],
            'tag_list.*' => ['string', 'max:100'],
        ];
    }
}
