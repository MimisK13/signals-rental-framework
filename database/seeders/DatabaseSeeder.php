<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Activities\Database\Seeders\ActivitySeeder;
use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Communications\Database\Seeders\CommunicationTemplateSeeder;
use App\Domain\Communications\Database\Seeders\EmailTemplateSeeder;
use App\Domain\Currency\Database\Seeders\CurrencySeeder;
use App\Domain\Currency\Database\Seeders\ExchangeRateSeeder;
use App\Domain\CustomViews\Database\Seeders\ViewSeeder;
use App\Domain\Documents\Database\Seeders\DocumentTemplateSeeder;
use App\Domain\Lists\Database\Seeders\ListOfValuesSeeder;
use App\Domain\Notifications\Database\Seeders\NotificationTypeSeeder;
use App\Domain\Pricing\Database\Seeders\DiscountCategorySeeder;
use App\Domain\Pricing\Database\Seeders\PriceCategorySeeder;
use App\Domain\Products\Database\Seeders\ProductGroupSeeder;
use App\Domain\Products\Database\Seeders\ProductSeeder;
use App\Domain\Rates\Database\Seeders\RateDefinitionPresetSeeder;
use App\Domain\ReferenceData\Database\Seeders\CostGroupSeeder;
use App\Domain\ReferenceData\Database\Seeders\CountrySeeder;
use App\Domain\ReferenceData\Database\Seeders\RevenueGroupSeeder;
use App\Domain\Stores\Database\Seeders\StoreSeeder;
use App\Domain\Tax\Database\Seeders\TaxCategorySeeder;
use App\Domain\Tax\Database\Seeders\TaxClassSeeder;
use App\Domain\Tax\Database\Seeders\TaxRateSeeder;
use App\Domain\Tax\Database\Seeders\TaxRuleSeeder;
use App\Domain\Tax\Database\Seeders\TaxZoneRuleSeeder;
use App\Domain\Tax\Database\Seeders\TaxZoneSeeder;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
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
    }
}
