<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Data;

use Spatie\LaravelData\Data;

final class SendQuotationVersionData extends Data
{
    public function __construct(
        public ?int $sent_to = null,
        public string $sent_via = 'manual',
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'sent_to' => ['sometimes', 'nullable', 'integer', 'exists:members,id'],
            'sent_via' => ['sometimes', 'string', 'in:manual,email,portal'],
        ];
    }
}
