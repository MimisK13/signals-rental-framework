<?php

declare(strict_types=1);

namespace App\Domain\Stores\Database\Seeders;

use App\Domain\Stores\Models\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Store::query()->exists()) {
            return;
        }

        Store::create([
            'name' => 'Main Warehouse',
            'is_default' => true,
            'country_code' => 'GB',
        ]);
    }
}
