<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Data;

use Spatie\LaravelData\Data;

final class CreateQuotationData extends Data
{
    public function __construct(
        public string $subject,
        public int $store_id,
        public ?int $project_id = null,
        public ?int $member_id = null,
        public ?int $venue_id = null,
        public ?int $owned_by = null,
        public ?string $reference = null,
        public ?string $description = null,
        public ?string $starts_at = null,
        public ?string $ends_at = null,
        public ?string $valid_until = null,
        public ?string $currency_code = null,
        public ?bool $prices_include_tax = null,
        public ?string $version_label = null,
        public ?string $version_notes = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'project_id' => ['sometimes', 'nullable', 'integer', 'exists:projects,id'],
            'member_id' => ['required_without:project_id', 'nullable', 'integer', 'exists:members,id'],
            'venue_id' => ['sometimes', 'nullable', 'integer', 'exists:members,id'],
            'owned_by' => ['sometimes', 'nullable', 'integer', 'exists:members,id'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'valid_until' => ['sometimes', 'nullable', 'date'],
            'currency_code' => ['sometimes', 'nullable', 'string', 'size:3'],
            'prices_include_tax' => ['sometimes', 'nullable', 'boolean'],
            'version_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'version_notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
