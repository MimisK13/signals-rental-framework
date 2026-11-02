<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Data;

use Spatie\LaravelData\Data;

final class RestructureQuotationItemsData extends Data
{
    /** @param array<int, array{id: int, depth: int}> $nodes */
    public function __construct(
        public array $nodes,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'nodes' => ['required', 'array'],
            'nodes.*.id' => ['required', 'integer', 'distinct'],
            'nodes.*.depth' => ['required', 'integer', 'min:1'],
        ];
    }
}
