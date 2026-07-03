<?php

namespace App\Data\Opportunities;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class ToggleOpportunitySurchargeGroupData extends Data
{
    public function __construct(
        #[Required]
        public int $surcharge_group_id,
        #[Required]
        public bool $is_active,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'surcharge_group_id' => ['required', 'integer', Rule::exists('surcharge_groups', 'id')],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
