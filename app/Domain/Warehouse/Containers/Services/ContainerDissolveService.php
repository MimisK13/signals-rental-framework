<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Services;

use App\Domain\Stock\Models\StockLevel;
use App\Domain\Warehouse\Containers\Enums\ContainerDissolveReason;
use App\Domain\Warehouse\Containers\Enums\ContainerItemUnpackReason;
use App\Domain\Warehouse\Containers\Enums\ContainerStatus;
use App\Domain\Warehouse\Containers\Models\Container;
use App\Domain\Warehouse\Containers\Models\ContainerItem;
use App\Services\Availability\ContainerDemandResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Shared dissolve mechanics for manual dissolution (P51) and transport dispatch (P52).
 *
 * Container-side mutations are plain Eloquent writes — they are intentionally NOT
 * replayed by Verbs.
 */
final class ContainerDissolveService
{
    /**
     * Depth-first dissolve of a container and any nested active containers.
     */
    public function dissolveTree(
        Container $container,
        ContainerDissolveReason $reason,
        ?int $opportunityId = null,
    ): int {
        $container->refresh();
        $closed = 0;

        foreach ($container->activeItems()->with('serialisedItem')->get() as $item) {
            $nested = Container::query()
                ->active()
                ->where('serialised_item_id', $item->serialised_item_id)
                ->first();

            if ($nested !== null && $nested->id !== $container->id) {
                $closed += $this->dissolveTree($nested, $reason, $opportunityId);
            }
        }

        foreach ($container->activeItems()->get() as $item) {
            $this->closeMembership($container, $item);
            $closed++;
        }

        $container->update([
            'status' => ContainerStatus::Dissolved,
            'dissolved_at' => Carbon::now('UTC'),
            'dissolved_by_user_id' => Auth::id(),
            'dissolved_reason' => $reason->value,
            'opportunity_id' => $opportunityId ?? $container->opportunity_id,
            'parent_container_id' => null,
        ]);

        return $closed;
    }

    public function closeMembership(Container $container, ContainerItem $item): void
    {
        $item->forceFill([
            'unpacked_at' => Carbon::now('UTC'),
            'unpacked_reason' => ContainerItemUnpackReason::Dissolved,
        ])->save();

        StockLevel::query()
            ->whereKey($item->serialised_item_id)
            ->update([
                'container_stock_level_id' => null,
                'container_mode' => null,
            ]);

        $item->setRelation('container', $container);
        app(ContainerDemandResolver::class)->releaseDemands($item);
    }
}
