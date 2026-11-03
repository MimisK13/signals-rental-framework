<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Data;

use Spatie\LaravelData\Data;

/**
 * Input DTO for changing a line item's quantity.
 */
class ChangeItemQuantityData extends Data
{
    public function __construct(
        public string $quantity,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'min:0'],
        ];
    }
}
