<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Data;

use Spatie\LaravelData\Data;

/**
 * Input DTO for nesting an asset assignment inside a container stock level.
 */
class SetAssetContainerData extends Data
{
    public function __construct(
        public int $container_stock_level_id,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'container_stock_level_id' => ['required', 'integer', 'exists:stock_levels,id'],
        ];
    }
}
