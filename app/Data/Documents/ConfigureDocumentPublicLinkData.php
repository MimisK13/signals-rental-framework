<?php

namespace App\Data\Documents;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class ConfigureDocumentPublicLinkData extends Data
{
    public function __construct(
        public string|null|Optional $expires_at = new Optional,
        public string|null|Optional $pin = new Optional,
        public bool $clear_pin = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'pin' => ['sometimes', 'nullable', 'string', 'min:4', 'max:32'],
            'clear_pin' => ['sometimes', 'boolean'],
        ];
    }
}
