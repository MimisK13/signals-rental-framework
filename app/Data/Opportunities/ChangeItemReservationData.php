<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * Input for changing an item's explicit owned-capacity reservation.
 *
 * Zero is an explicit release. Null is intentionally not accepted because it
 * is reserved for legacy rows and is not a normal mutation state.
 */
class ChangeItemReservationData extends Data
{
    public function __construct(
        public string $reserved_quantity,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'reserved_quantity' => ['required', 'numeric', 'min:0'],
        ];
    }
}
