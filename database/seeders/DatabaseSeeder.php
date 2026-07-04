<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CountrySeeder::class,
            ListOfValuesSeeder::class,
            CurrencySeeder::class,
            ExchangeRateSeeder::class,
            StoreSeeder::class,
            TaxClassSeeder::class,
            TaxZoneSeeder::class,
            TaxCategorySeeder::class,
            TaxRateSeeder::class,
            TaxRuleSeeder::class,
            TaxZoneRuleSeeder::class,
            RevenueGroupSeeder::class,
            CostGroupSeeder::class,
            ProductGroupSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            EmailTemplateSeeder::class,
            NotificationTypeSeeder::class,
            DocumentTemplateSeeder::class,
            CommunicationTemplateSeeder::class,
            ViewSeeder::class,
            RateDefinitionPresetSeeder::class,
            DiscountCategorySeeder::class,
            PriceCategorySeeder::class,
            ProductSeeder::class,
            ActivitySeeder::class,
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
