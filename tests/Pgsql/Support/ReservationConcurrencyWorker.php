<?php

declare(strict_types=1);

use App\Domain\Administration\Models\User;
use App\Domain\Assets\Enums\AssetLifecycleEventType;
use App\Domain\Assets\Enums\AssetLifecycleStatus;
use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Services\AssetLifecycleTransitioner;
use App\Domain\Availability\Services\ProductStoreCapacityLock;
use App\Domain\Projects\Opportunities\Actions\AllocateAsset;
use App\Domain\Projects\Opportunities\Actions\ChangeItemReservation;
use App\Domain\Projects\Opportunities\Actions\ChangeOpportunityStatus;
use App\Domain\Projects\Opportunities\Actions\ReturnBulkQuantity;
use App\Domain\Projects\Opportunities\Actions\UpdateOpportunity;
use App\Domain\Projects\Opportunities\Data\AllocateAssetData;
use App\Domain\Projects\Opportunities\Data\BulkReturnData;
use App\Domain\Projects\Opportunities\Data\ChangeItemReservationData;
use App\Domain\Projects\Opportunities\Data\UpdateOpportunityData;
use App\Domain\Projects\Opportunities\Enums\OpportunityStatus;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Stock\Actions\CreateStockTransaction;
use App\Domain\Stock\Actions\UpdateStockLevel;
use App\Domain\Stock\Data\CreateStockTransactionData;
use App\Domain\Stock\Data\UpdateStockLevelData;
use App\Domain\Stock\Enums\TransactionType;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Warehouse\Containers\Actions\VerifyKitCheckin;
use App\Domain\Warehouse\Containers\Models\Container;
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
