<?php

namespace App\Data\Documents;

use Spatie\LaravelData\Data;

class CreateDocumentPublicLinkData extends Data
{
    public function __construct(
        public ?string $expires_at = null,
        public ?string $pin = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'expires_at' => ['nullable', 'date'],
            'pin' => ['nullable', 'string', 'min:4', 'max:32'],
        ];
    }
}
