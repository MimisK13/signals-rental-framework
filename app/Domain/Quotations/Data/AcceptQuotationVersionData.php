<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Data;

use Spatie\LaravelData\Data;

final class AcceptQuotationVersionData extends Data
{
    public function __construct(
        public ?int $accepted_by = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'accepted_by' => ['sometimes', 'nullable', 'integer', 'exists:members,id'],
        ];
    }
}
