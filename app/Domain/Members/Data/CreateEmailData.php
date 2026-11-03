<?php

declare(strict_types=1);

namespace App\Domain\Members\Data;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class CreateEmailData extends Data
{
    public function __construct(
        #[Required]
        public string $address,
        public ?int $type_id = null,
        public bool $is_primary = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'address' => ['required', 'string', 'email', 'max:255'],
            'type_id' => ['sometimes', 'nullable', 'integer', 'exists:list_values,id'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
