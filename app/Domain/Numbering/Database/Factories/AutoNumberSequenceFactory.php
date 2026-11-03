<?php

declare(strict_types=1);

namespace App\Domain\Numbering\Database\Factories;

use App\Domain\CustomFields\Models\CustomField;
use App\Domain\Numbering\Models\AutoNumberSequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutoNumberSequence>
 */
class AutoNumberSequenceFactory extends Factory
{
    protected $model = AutoNumberSequence::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'custom_field_id' => CustomField::factory(),
            'next_value' => 1,
        ];
    }

    public function withPrefix(string $prefix): static
    {
        return $this->state(fn () => ['prefix' => $prefix]);
    }

    public function withSuffix(string $suffix): static
    {
        return $this->state(fn () => ['suffix' => $suffix]);
    }
}
