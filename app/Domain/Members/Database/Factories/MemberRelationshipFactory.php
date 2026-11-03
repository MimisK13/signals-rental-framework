<?php

declare(strict_types=1);

namespace App\Domain\Members\Database\Factories;

use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\MemberRelationship;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberRelationship>
 */
class MemberRelationshipFactory extends Factory
{
    protected $model = MemberRelationship::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory()->contact(),
            'related_member_id' => Member::factory()->organisation(),
            'relationship_type' => 'Employee',
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }
}
