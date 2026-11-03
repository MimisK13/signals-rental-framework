<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Data;

use Spatie\LaravelData\Data;

/**
 * Input DTO for renaming a custom line-item grouping (section).
 */
class RenameOpportunitySectionData extends Data
{
    public function __construct(
        public string $name,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
