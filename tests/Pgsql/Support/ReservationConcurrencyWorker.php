<?php

declare(strict_types=1);

use App\Actions\Containers\VerifyKitCheckin;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ChangeItemReservation;
use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\ReturnBulkQuantity;
use App\Actions\Opportunities\UpdateOpportunity;
use App\Actions\Products\CreateStockTransaction;
use App\Actions\Products\UpdateStockLevel;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\BulkReturnData;
use App\Data\Opportunities\ChangeItemReservationData;
use App\Data\Opportunities\UpdateOpportunityData;
use App\Data\Products\CreateStockTransactionData;
use App\Data\Products\UpdateStockLevelData;
use App\Enums\AssetLifecycleEventType;
use App\Enums\AssetLifecycleStatus;
use App\Enums\OpportunityStatus;
use App\Enums\TransactionType;
use App\Models\Asset;
use App\Models\Container;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\StockLevel;
use App\Models\User;
use App\Services\Assets\AssetLifecycleTransitioner;
use App\Services\Availability\ProductStoreCapacityLock;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'pgsql_testing']);
DB::setDefaultConnection('pgsql_testing');
Gate::define('opportunities.edit', static fn (): bool => true);
Gate::define('stock.adjust', static fn (): bool => true);
Gate::define('containers.view', static fn (): bool => true);
Auth::login(User::query()->firstOrFail());

$operation = (string) ($argv[1] ?? 'reserve');
$target = (string) ($argv[2] ?? '0');
$targetId = (int) $target;
$quantity = (string) ($argv[3] ?? '0');
$barrier = (string) ($argv[4] ?? '');

fwrite(STDOUT, "ready\n");
fflush(STDOUT);

while (! file_exists($barrier)) {
    usleep(1000);
}

try {
    if ($operation === 'stock_reduce') {
        $stockLevel = StockLevel::query()->findOrFail($targetId);
        app(UpdateStockLevel::class)($stockLevel, UpdateStockLevelData::from([
            'quantity_held' => $quantity,
        ]));
    } elseif ($operation === 'stock_transaction_reduce') {
        app(CreateStockTransaction::class)(CreateStockTransactionData::from([
            'stock_level_id' => $targetId,
            'transaction_type' => TransactionType::Decrease->value,
            'quantity' => $quantity,
        ]));
    } elseif ($operation === 'allocate') {
        $item = OpportunityItem::query()->findOrFail($targetId);
        app(AllocateAsset::class)($item, AllocateAssetData::from([
            'stock_level_id' => (int) $quantity,
        ]));
    } elseif ($operation === 'lock_order') {
        DB::transaction(function () use ($target): void {
            app(ProductStoreCapacityLock::class)->acquireMany(json_decode($target, true, flags: JSON_THROW_ON_ERROR));
        });
    } elseif ($operation === 'lifecycle_missing') {
        app(AssetLifecycleTransitioner::class)->transition(
            Asset::query()->findOrFail($targetId),
            AssetLifecycleEventType::MarkedMissing,
            AssetLifecycleStatus::Missing,
        );
    } elseif ($operation === 'status_reserved') {
        app(ChangeOpportunityStatus::class)(
            Opportunity::query()->findOrFail($targetId),
            OpportunityStatus::QuotationReserved,
        );
    } elseif ($operation === 'store_move') {
        app(UpdateOpportunity::class)(
            Opportunity::query()->findOrFail($targetId),
            UpdateOpportunityData::from(['store_id' => (int) $quantity]),
        );
    } elseif ($operation === 'verify_container') {
        app(VerifyKitCheckin::class)(Container::query()->findOrFail($targetId));
    } elseif ($operation === 'return_bulk') {
        app(ReturnBulkQuantity::class)(
            OpportunityItem::query()->findOrFail($targetId),
            BulkReturnData::from(['quantity' => $quantity]),
        );
    } else {
        $item = OpportunityItem::query()->findOrFail($targetId);
        (new ChangeItemReservation)($item, ChangeItemReservationData::from([
            'reserved_quantity' => $quantity,
        ]));
    }

    fwrite(STDOUT, json_encode(['ok' => true], JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'exception' => $exception::class,
        'message' => $exception->getMessage(),
    ], JSON_THROW_ON_ERROR)."\n");
}
