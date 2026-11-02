<?php

declare(strict_types=1);

namespace App\Domain\Lists\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class UpdateListValueData extends Data
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string|Optional $name = new Optional,
        public int|null|Optional $parent_id = new Optional,
        public int|Optional $sort_order = new Optional,
        public bool|Optional $is_active = new Optional,
        public array|null|Optional $metadata = new Optional,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:list_values,id'],
            'sort_order' => ['sometimes', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
