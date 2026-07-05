<?php

namespace App\Data\Shortages;

use App\Enums\ShortageResolutionStatus;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * Input for the unified POST transition endpoint
 * (/shortage_resolutions/{id}/transition, shortage-resolution-sub-hires.md §8.3).
 */
class ApplyShortageResolutionTransitionData extends Data
{
    public function __construct(
        public string $status,
        public ?string $reason = null,
    ) {}

    public function targetStatus(): ShortageResolutionStatus
    {
        return ShortageResolutionStatus::from($this->status);
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(ShortageResolutionStatus::class)],
            'reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
