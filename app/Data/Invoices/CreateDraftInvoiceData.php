<?php

namespace App\Data\Invoices;

use App\Enums\InvoiceType;
use App\Enums\MembershipType;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class CreateDraftInvoiceData extends Data
{
    public function __construct(
        public int $member_id,
        public int $store_id,
        public ?int $opportunity_id = null,
        public string $type = InvoiceType::Standard->value,
        public ?string $currency = null,
        public ?bool $prices_include_tax = null,
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
            'member_id' => [
                'required',
                'integer',
                Rule::exists('members', 'id')->where('membership_type', MembershipType::Organisation->value),
            ],
            'store_id' => ['required', 'integer', Rule::exists('stores', 'id')],
            'opportunity_id' => ['nullable', 'integer', Rule::exists('opportunities', 'id')],
            'type' => ['required', 'string', Rule::in(array_column(InvoiceType::cases(), 'value'))],
            'currency' => ['nullable', 'string', 'size:3'],
            'prices_include_tax' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:255'],
            'tag_list' => ['nullable', 'array'],
            'tag_list.*' => ['string', 'max:100'],
        ];
    }
}
