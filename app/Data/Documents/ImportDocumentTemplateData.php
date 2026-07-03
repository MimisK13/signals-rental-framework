<?php

namespace App\Data\Documents;

use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;

class ImportDocumentTemplateData extends Data
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        #[Exists('stores', 'id')]
        public int $store_id,
        public array $payload,
        #[Max(255)]
        public ?string $slug = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'payload' => ['required', 'array'],
            'slug' => ['nullable', 'string', 'max:255'],
        ];
    }
}
