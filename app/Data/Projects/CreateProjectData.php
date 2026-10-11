<?php

namespace App\Data\Projects;

use App\Enums\ProjectStatus;
use Spatie\LaravelData\Data;

class CreateProjectData extends Data
{
    public function __construct(
        public string $name,
        public ?int $member_id = null,
        public ?int $venue_id = null,
        public ?int $owned_by = null,
        public string $status = 'draft',
        public ?string $starts_at = null,
        public ?string $ends_at = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'member_id' => ['sometimes', 'nullable', 'integer', 'exists:members,id'],
            'venue_id' => ['sometimes', 'nullable', 'integer', 'exists:members,id'],
            'owned_by' => ['sometimes', 'nullable', 'integer', 'exists:members,id'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', array_column(ProjectStatus::cases(), 'value'))],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
