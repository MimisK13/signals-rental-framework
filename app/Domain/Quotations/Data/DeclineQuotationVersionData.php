<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Data;

use Spatie\LaravelData\Data;

final class DeclineQuotationVersionData extends Data
{
    public function __construct(
        public ?string $reason = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
