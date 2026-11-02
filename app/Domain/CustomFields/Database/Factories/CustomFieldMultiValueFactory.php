<?php

declare(strict_types=1);

namespace App\Domain\CustomFields\Database\Factories;

use App\Domain\CustomFields\Models\CustomFieldMultiValue;
use App\Domain\CustomFields\Models\CustomFieldValue;
use App\Domain\Lists\Models\ListValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomFieldMultiValue>
 */
class CustomFieldMultiValueFactory extends Factory
{
    protected $model = CustomFieldMultiValue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'custom_field_value_id' => CustomFieldValue::factory(),
            'list_value_id' => ListValue::factory(),
        ];
    }
}
