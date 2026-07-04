<?php

namespace App\Services\Containers;

use App\Data\Containers\ContainerData;
use App\Data\Containers\ContainerItemData;
use App\Enums\ContainerUnsealReason;
use App\Events\AuditableEvent;
use App\Models\Container;
use App\Models\ContainerItem;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Auth;

/**
 * Bridges container mutations onto the audit log and outbound webhook bus.
 */
class ContainerEventRecorder
{
    public function created(Container $container): void
    {
        event(new AuditableEvent($container, 'container.created'));

        app(WebhookService::class)->dispatch('container.created', [
            'container' => ContainerData::fromModel($container)->toArray(),
            'serialised_item_id' => $container->serialised_item_id,
            'product_id' => $container->product_id,
            'scan_mode' => $container->scan_mode->value,
            'is_temporary' => $container->is_temporary,
            'user_id' => Auth::id(),
        ]);
    }

    public function itemPacked(Container $container, ContainerItem $item): void
    {
        event(new AuditableEvent($item, 'container.item.packed', metadata: [
            'container_id' => $container->id,
            'container_uuid' => $container->uuid,
        ]));

        app(WebhookService::class)->dispatch('container.item.packed', [
            'container' => ContainerData::fromModel($container)->toArray(),
            'container_item' => ContainerItemData::fromModel($item)->toArray(),
            'container_id' => $container->id,
            'container_uuid' => $container->uuid,
            'container_barcode' => $container->barcode,
            'serialised_item_id' => $item->serialised_item_id,
            'product_id' => $item->product_id,
            'user_id' => Auth::id(),
        ]);
    }

    public function itemUnpacked(Container $container, ContainerItem $item): void
    {
        event(new AuditableEvent($item, 'container.item.unpacked', metadata: [
            'container_id' => $container->id,
            'container_uuid' => $container->uuid,
        ]));

        app(WebhookService::class)->dispatch('container.item.unpacked', [
            'container' => ContainerData::fromModel($container)->toArray(),
            'container_item' => ContainerItemData::fromModel($item)->toArray(),
            'container_id' => $container->id,
            'container_uuid' => $container->uuid,
            'container_barcode' => $container->barcode,
            'serialised_item_id' => $item->serialised_item_id,
            'product_id' => $item->product_id,
            'reason' => $item->unpacked_reason?->value,
            'user_id' => Auth::id(),
        ]);
    }

    public function sealed(Container $container, int $itemCount): void
    {
        event(new AuditableEvent($container, 'container.sealed', newValues: [
            'status' => $container->status->value,
            'sealed_at' => $container->sealed_at?->toIso8601String(),
        ]));

        app(WebhookService::class)->dispatch('container.sealed', [
            'container' => ContainerData::fromModel($container)->toArray(),
            'container_id' => $container->id,
            'container_uuid' => $container->uuid,
            'item_count' => $itemCount,
            'user_id' => Auth::id(),
        ]);
    }

    public function unsealed(Container $container, ContainerUnsealReason $reason): void
    {
        event(new AuditableEvent($container, 'container.unsealed', newValues: [
            'status' => $container->status->value,
            'reason' => $reason->value,
        ]));

        app(WebhookService::class)->dispatch('container.unsealed', [
            'container' => ContainerData::fromModel($container)->toArray(),
            'container_id' => $container->id,
            'container_uuid' => $container->uuid,
            'reason' => $reason->value,
            'user_id' => Auth::id(),
        ]);
    }
}
