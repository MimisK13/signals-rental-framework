<?php

namespace Database\Seeders;

use App\Models\ProductGroup;
use Illuminate\Database\Seeder;

class ProductGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            ['name' => 'Audio', 'description' => 'Professional sound, microphones and audio systems.', 'parent' => null],
            ['name' => 'Lighting - Generic', 'description' => 'Generic LED and conventional lighting fixtures.', 'parent' => null],
            ['name' => 'Lighting - Moving Heads', 'description' => 'Automated moving light fixtures.', 'parent' => null],
            ['name' => 'Video', 'description' => 'Displays, projectors, cameras and video systems.', 'parent' => null],
            ['name' => 'Staging', 'description' => 'Stages, decks and event structures.', 'parent' => null],
            ['name' => 'Power', 'description' => 'Power distribution and electrical equipment.', 'parent' => null],
            ['name' => 'Rigging', 'description' => 'Rigging hardware and load-bearing equipment.', 'parent' => null],
            ['name' => 'Furniture', 'description' => 'Event and production furniture.', 'parent' => null],
            ['name' => 'Transport', 'description' => 'Cases, trunks and transport equipment.', 'parent' => null],
            ['name' => 'Consumables', 'description' => 'Consumable production supplies.', 'parent' => null],
            ['name' => 'Audio / Mixing Desks', 'description' => 'Digital and analogue mixing consoles.', 'parent' => 'Audio'],
            ['name' => 'Audio / Wireless', 'description' => 'Wireless microphone and in-ear monitoring systems.', 'parent' => 'Audio'],
            ['name' => 'Audio / Microphones', 'description' => 'Vocal, instrument, condenser and lavalier microphones.', 'parent' => 'Audio'],
            ['name' => 'Audio / Amplifiers', 'description' => 'Power amplifiers and amplified speaker controllers.', 'parent' => 'Audio'],
            ['name' => 'Audio / DI & Signal', 'description' => 'DI boxes, splitters and signal-processing hardware.', 'parent' => 'Audio'],
            ['name' => 'Audio / Cables', 'description' => 'Audio cables and connectivity accessories.', 'parent' => 'Audio'],
            ['name' => 'Audio / Cases', 'description' => 'Audio equipment cases and flightcases.', 'parent' => 'Audio'],
        ];

        foreach ($groups as $index => $group) {
            ProductGroup::query()->updateOrCreate(
                ['name' => $group['name']],
                [
                    'description' => $group['description'],
                    'parent_id' => $group['parent'] !== null
                        ? ProductGroup::query()->where('name', $group['parent'])->value('id')
                        : null,
                    'sort_order' => $index,
                ],
            );
        }
    }
}
