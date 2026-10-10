<?php

namespace App\Console\Commands;

use App\Enums\AssetOwnershipType;
use App\Enums\StockCategory;
use App\Models\Asset;
use App\Models\StockLevel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillAssetsFromStockLevels extends Command
{
    protected $signature = 'assets:backfill-from-stock-levels';

    protected $description = 'Create financial Assets for eligible serialized StockLevels';

    public function handle(): int
    {
        $created = 0;
        $skipped = 0;

        StockLevel::query()
            ->where('stock_category', StockCategory::SerialisedStock->value)
            ->where('is_virtual', false)
            ->whereNull('asset_id')
            ->orderBy('id')
            ->chunkById(100, function ($stockLevels) use (&$created, &$skipped): void {
                foreach ($stockLevels as $stockLevel) {
                    if (bccomp((string) $stockLevel->quantity_held, '1', 2) !== 0) {
                        $this->warn("Skipped StockLevel {$stockLevel->id}: serialized quantity is not 1.");
                        $skipped++;

                        continue;
                    }

                    DB::transaction(function () use ($stockLevel, &$created): void {
                        $stockLevel->refresh();

                        if ($stockLevel->asset_id !== null) {
                            return;
                        }

                        $asset = Asset::create([
                            'product_id' => $stockLevel->product_id,
                            'ownership_type' => AssetOwnershipType::Unknown,
                        ]);

                        $stockLevel->forceFill(['asset_id' => $asset->id])->saveQuietly();
                        $created++;
                    });
                }
            });

        $this->info("Created {$created} Asset(s); skipped {$skipped} invalid StockLevel(s).");

        return self::SUCCESS;
    }
}
