<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * Input DTO for changing a line item's per-item hire window. A null date inherits
 * the parent opportunity's corresponding date.
 */
class ChangeItemDatesData extends Data
{
    public function __construct(
        public ?string $starts_at = null,
        public ?string $ends_at = null,
        public string|null|Optional $planning_starts_at = new Optional,
        public string|null|Optional $planning_ends_at = new Optional,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'planning_starts_at' => ['sometimes', 'nullable', 'date'],
            'planning_ends_at' => ['sometimes', 'nullable', 'date', 'after:planning_starts_at'],
        ];
    }
}
