<?php

namespace App\Verbs\Events\VirtualStock;

use App\Enums\StockMethod;
use App\Models\Product;
use App\Models\VirtualStockIntake;
use App\Services\VirtualStock\VirtualStockAssetProvisioner;
use App\Verbs\States\VirtualStockIntakeState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Provisions replay-stable serialised virtual stock_levels rows and per-asset
 * negative supply demands for a confirmed/received intake line.
 */
class VirtualStockIntakeSerialisedAssetsProvisioned extends Event
{
    /**
     * @param  list<array{stock_level_id: int, supply_demand_id: int, sequence: int, asset_number: string}>  $assets
     */
    public function __construct(
        #[StateId(VirtualStockIntakeState::class)]
        public int $state_id,
        public array $assets = [],
        public ?string $provisioned_at = null,
    ) {}

    public function validate(VirtualStockIntakeState $state): void
    {
        $this->assert(
            ! $state->is_deleted,
            'Deleted virtual stock intakes cannot provision virtual assets.',
        );

        $this->assert(
            ! $state->serialised_assets_provisioned,
            'Serialised virtual assets have already been provisioned for this intake.',
        );

        $product = Product::query()->find($state->product_id);

        $this->assert(
            $product !== null && $product->stock_method === StockMethod::Serialised,
            'Only serialised products can provision virtual assets.',
        );

        $this->assert(
            count($this->assets) === $state->quantity,
            'The provisioned virtual asset count must match the intake quantity.',
        );
    }

    public function apply(VirtualStockIntakeState $state): void
    {
        $state->serialised_assets_provisioned = true;
        $state->serialised_asset_stock_level_ids = array_map(
            static fn (array $asset): int => (int) $asset['stock_level_id'],
            $this->assets,
        );
        $state->last_event_at = $this->provisioned_at !== null
            ? CarbonImmutable::parse($this->provisioned_at)
            : CarbonImmutable::now('UTC');
    }

    public function handle(VirtualStockIntakeState $state): void
    {
        $intake = VirtualStockIntake::query()->find($state->virtual_stock_intake_id);

        if ($intake === null) {
            return;
        }

        app(VirtualStockAssetProvisioner::class)->provision($intake, $this->assets);

        VirtualStockIntake::query()
            ->whereKey($state->virtual_stock_intake_id)
            ->update(['updated_at' => $state->last_event_at]);
    }
}
