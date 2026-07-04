<?php

namespace App\Data\Shortages;

use Spatie\LaravelData\Data;

/**
 * Input for applying a batch shortage resolution across multiple line items.
 */
class ApplyBatchResolutionData extends Data
{
    public function __construct(
        /** @var list<int> */
        public array $opportunity_item_ids,
        public string $resolver_key,
        public int $option_index = 0,
        public ?string $notes = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'opportunity_item_ids' => ['required', 'array', 'min:2'],
            'opportunity_item_ids.*' => ['required', 'integer', 'distinct', 'exists:opportunity_items,id'],
            'resolver_key' => ['required', 'string', 'max:64'],
            'option_index' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
