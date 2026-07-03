<?php

namespace App\Data\Invoices;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;

/**
 * Input for issuing a credit note against an invoice.
 */
class IssueCreditNoteData extends Data
{
    /**
     * @param  list<array{invoice_item_id: int, quantity?: string|null}>|null  $lines  null = full credit
     */
    public function __construct(
        /** @var list<array{invoice_item_id: int, quantity?: string|null}>|null */
        public ?array $lines = null,
        #[Max(65535)]
        public ?string $notes = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'lines' => ['nullable', 'array'],
            'lines.*.invoice_item_id' => ['required', 'integer'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:65535'],
        ];
    }
}
