<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Data;

use App\Domain\Quotations\Enums\QuotationVersionType;
use Spatie\LaravelData\Data;

final class CreateQuotationVersionData extends Data
{
    public function __construct(
        public string $version_type = 'revision',
        public ?int $source_version_id = null,
        public ?string $label = null,
        public ?string $notes = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'version_type' => ['sometimes', 'string', 'in:'.implode(',', [
                QuotationVersionType::Revision->value,
                QuotationVersionType::Alternative->value,
            ])],
            'source_version_id' => ['sometimes', 'nullable', 'integer', 'exists:quotation_versions,id'],
            'label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
