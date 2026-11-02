<?php

declare(strict_types=1);

namespace App\Domain\Rates\Database\Seeders;

use App\Domain\Rates\Models\RateDefinition;
use App\Domain\Rates\Services\RateEngine\Presets\RatePresets;
use Illuminate\Database\Seeder;

final class RateDefinitionPresetSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RatePresets::all() as $preset) {
            RateDefinition::query()->updateOrCreate(
                ['preset_slug' => $preset['slug']],
                [
                    'name' => $preset['name'],
                    'description' => $preset['description'],
                    'calculation_strategy' => $preset['calculation_strategy'],
                    'base_period' => $preset['base_period'],
                    'enabled_modifiers' => $preset['enabled_modifiers'],
                    'strategy_config' => $preset['strategy_config'],
                    'modifier_configs' => $preset['modifier_configs'],
                    'is_preset' => true,
                ],
            );
        }
    }
}
