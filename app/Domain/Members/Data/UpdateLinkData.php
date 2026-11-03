<?php

declare(strict_types=1);

namespace App\Domain\Members\Data;

use Spatie\LaravelData\Data;

class UpdateLinkData extends Data
{
    public function __construct(
        public ?string $url = null,
        public ?string $name = null,
        public ?int $type_id = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'url' => ['sometimes', 'string', 'url', 'max:2048'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'type_id' => ['sometimes', 'nullable', 'integer', 'exists:list_values,id'],
        ];
    }
}
