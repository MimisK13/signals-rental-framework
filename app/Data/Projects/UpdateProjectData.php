<?php

namespace App\Data\Projects;

use App\Enums\ProjectStatus;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UpdateProjectData extends Data
{
    public function __construct(
        public string|Optional $name = new Optional,
        public int|null|Optional $member_id = new Optional,
        public int|null|Optional $venue_id = new Optional,
        public int|null|Optional $owned_by = new Optional,
        public string|Optional $status = new Optional,
        public string|null|Optional $starts_at = new Optional,
        public string|null|Optional $ends_at = new Optional,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'member_id' => ['sometimes', 'nullable', 'integer', 'exists:members,id'],
            'venue_id' => ['sometimes', 'nullable', 'integer', 'exists:members,id'],
            'owned_by' => ['sometimes', 'nullable', 'integer', 'exists:members,id'],
            'status' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', array_column(ProjectStatus::cases(), 'value'))],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
