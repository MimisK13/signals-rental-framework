<?php

namespace App\Data\Documents;

use App\Enums\DocumentSequenceResetFrequency;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Sometimes;
use Spatie\LaravelData\Data;

class UpdateDocumentSequenceData extends Data
{
    public function __construct(
        #[Sometimes, Max(50)]
        public ?string $prefix = null,
        #[Sometimes, Max(50)]
        public ?string $suffix = null,
        public ?bool $include_year = null,
        public ?bool $include_month = null,
        #[Sometimes, Min(1)]
        public ?int $next_number = null,
        #[Sometimes, Min(1), Max(12)]
        public ?int $padding = null,
        public ?string $reset_frequency = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'prefix' => ['sometimes', 'string', 'max:50'],
            'suffix' => ['sometimes', 'string', 'max:50'],
            'include_year' => ['sometimes', 'boolean'],
            'include_month' => ['sometimes', 'boolean'],
            'next_number' => ['sometimes', 'integer', 'min:1'],
            'padding' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'reset_frequency' => ['sometimes', 'string', Rule::enum(DocumentSequenceResetFrequency::class)],
        ];
    }
}
