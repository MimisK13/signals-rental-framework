<?php

declare(strict_types=1);

namespace App\Domain\Members\Database\Factories;

use App\Domain\Members\Models\Link;
use App\Domain\Members\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Link>
 */
class LinkFactory extends Factory
{
    protected $model = Link::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'linkable_type' => Member::class,
            'linkable_id' => Member::factory(),
            'url' => fake()->url(),
            'name' => fake()->optional()->domainName(),
        ];
    }
}
