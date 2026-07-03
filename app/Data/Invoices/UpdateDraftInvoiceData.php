<?php

namespace App\Data\Invoices;

use Spatie\LaravelData\Data;

class UpdateDraftInvoiceData extends Data
{
    public function __construct(
        public ?string $notes = null,
        public ?string $reference = null,
        public bool $notes_provided = false,
        public bool $reference_provided = false,
    ) {}

    public static function fromNotesAndReference(?string $notes, ?string $reference): self
    {
        return new self(
            notes: $notes,
            reference: $reference,
            notes_provided: true,
            reference_provided: true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
