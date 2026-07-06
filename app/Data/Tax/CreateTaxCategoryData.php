<?php

namespace App\Data\Tax;

use App\Enums\TaxCategoryType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Data;

class CreateTaxCategoryData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        #[Required, Max(32), Unique('tax_categories', 'slug')]
        public string $slug,
        #[Required]
        public TaxCategoryType $type,
        public ?string $description = null,
        public bool $is_default = false,
        public bool $is_active = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:32', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:tax_categories,slug'],
            'type' => ['required', 'string', 'in:standard,reduced,zero,exempt'],
            'description' => ['nullable', 'string'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
