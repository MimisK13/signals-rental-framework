<?php

namespace App\Data\Documents;

use App\Enums\DocumentSequenceResetFrequency;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Sometimes;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

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
    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'prefix' => ['sometimes', 'string', 'max:50'],
            'suffix' => ['sometimes', 'string', 'max:50'],
            'include_year' => ['sometimes', 'boolean'],
            'include_month' => ['sometimes', 'boolean'],
            'next_number' => [
                'sometimes',
                'integer',
                'min:1',
                function (string $attribute, mixed $value, \Closure $fail) use ($context): void {
                    $padding = $context?->payload['padding'] ?? null;

                    if ($padding === null) {
                        return;
                    }

                    if (strlen((string) $value) > (int) $padding) {
                        $fail('The next number must fit within the padding digit count ('.$padding.' digits).');
                    }
                },
            ],
            'padding' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'reset_frequency' => ['sometimes', 'string', Rule::enum(DocumentSequenceResetFrequency::class)],
        ];
    }

    /**
     * @throws ValidationException
     */
    public static function assertNextNumberFitsPadding(int $nextNumber, int $padding): void
    {
        if (strlen((string) $nextNumber) > $padding) {
            throw ValidationException::withMessages([
                'next_number' => ["The next number must fit within the padding digit count ({$padding} digits)."],
            ]);
        }
    }
}
