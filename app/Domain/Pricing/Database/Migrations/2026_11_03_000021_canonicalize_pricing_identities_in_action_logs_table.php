<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private const IDENTITIES = [
        'App\\Models\\PriceCategory' => 'App\\Domain\\Pricing\\Models\\PriceCategory',
        'App\\Models\\PriceCategoryTier' => 'App\\Domain\\Pricing\\Models\\PriceCategoryTier',
        'App\\Models\\DiscountCategory' => 'App\\Domain\\Pricing\\Models\\DiscountCategory',
        'App\\Models\\DiscountRule' => 'App\\Domain\\Pricing\\Models\\DiscountRule',
        'App\\Models\\BundleRule' => 'App\\Domain\\Pricing\\Models\\BundleRule',
        'App\\Models\\SurchargeGroup' => 'App\\Domain\\Pricing\\Models\\SurchargeGroup',
        'App\\Models\\Surcharge' => 'App\\Domain\\Pricing\\Models\\Surcharge',
    ];

    public function up(): void
    {
        foreach (self::IDENTITIES as $from => $to) {
            $this->replace($from, $to);
        }
    }

    public function down(): void
    {
        foreach (self::IDENTITIES as $from => $to) {
            $this->replace($to, $from);
        }
    }

    private function replace(string $from, string $to): void
    {
        DB::table('action_logs')->where('auditable_type', $from)->update(['auditable_type' => $to]);
    }
};
