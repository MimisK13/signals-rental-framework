<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Data;

use Spatie\LaravelData\Data;

/**
 * Input DTO for renaming a quote version's label.
 */
class ChangeVersionLabelData extends Data
{
    public function __construct(
        public ?string $label = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'label' => ['present', 'nullable', 'string', 'max:255'],
        ];
    }
}
