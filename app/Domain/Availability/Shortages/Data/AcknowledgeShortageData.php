<?php

declare(strict_types=1);

namespace App\Domain\Availability\Shortages\Data;

use Spatie\LaravelData\Data;

/**
 * Input for explicitly acknowledging an opportunity's shortages (the manual
 * counterpart to the gate's implicit Warn acknowledgement, §7.3).
 */
class AcknowledgeShortageData extends Data
{
    public function __construct(
        public ?string $notes = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
