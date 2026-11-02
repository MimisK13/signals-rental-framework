<?php

declare(strict_types=1);

namespace App\Domain\CustomViews\Database\Factories;

use App\Domain\CustomViews\Models\CustomView;
use App\Domain\CustomViews\Models\UserViewPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserViewPreference> */
final class UserViewPreferenceFactory extends Factory
{
    protected $model = UserViewPreference::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'entity_type' => 'members',
            'custom_view_id' => CustomView::factory(),
        ];
    }
}
