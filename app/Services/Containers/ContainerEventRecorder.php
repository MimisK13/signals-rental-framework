<?php

namespace App\Services\Containers;

use App\Data\Containers\ContainerData;
use App\Data\Containers\ContainerItemData;
use App\Enums\ContainerDissolveReason;
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

    public function dissolved(Container $container, ContainerDissolveReason $reason, int $itemCount): void
    {
        event(new AuditableEvent($container, 'container.dissolved', newValues: [
            'status' => $container->status->value,
            'reason' => $reason->value,
            'item_count' => $itemCount,
        ]));

        app(WebhookService::class)->dispatch('container.dissolved', [
            'container' => ContainerData::fromModel($container)->toArray(),
            'container_id' => $container->id,
            'container_uuid' => $container->uuid,
            'reason' => $reason->value,
            'opportunity_id' => $container->opportunity_id,
            'item_count' => $itemCount,
            'user_id' => Auth::id(),
        ]);
    }

    public function dispatched(Container $container, int $itemCount): void
    {
        event(new AuditableEvent($container, 'container.dispatched', newValues: [
            'status' => $container->status->value,
            'opportunity_id' => $container->opportunity_id,
            'item_count' => $itemCount,
        ]));

        app(WebhookService::class)->dispatch('container.dispatched', [
            'container' => ContainerData::fromModel($container)->toArray(),
            'container_id' => $container->id,
            'container_uuid' => $container->uuid,
            'opportunity_id' => $container->opportunity_id,
            'item_count' => $itemCount,
            'user_id' => Auth::id(),
        ]);
    }

    public function returned(Container $container, int $itemCount): void
    {
        event(new AuditableEvent($container, 'container.returned', newValues: [
            'status' => $container->status->value,
            'opportunity_id' => $container->opportunity_id,
            'item_count' => $itemCount,
            'checkin_verification_required_at' => $container->checkin_verification_required_at?->toIso8601String(),
        ]));

        app(WebhookService::class)->dispatch('container.returned', [
            'container' => ContainerData::fromModel($container)->toArray(),
            'container_id' => $container->id,
            'container_uuid' => $container->uuid,
            'opportunity_id' => $container->opportunity_id,
            'item_count' => $itemCount,
            'checkin_verification_required_at' => $container->checkin_verification_required_at?->toIso8601String(),
            'user_id' => Auth::id(),
        ]);
    }

    public function checkinVerified(Container $container): void
    {
        event(new AuditableEvent($container, 'container.checkin_verified', newValues: [
            'checkin_verification_required_at' => null,
        ]));
    }

    public function itemTransferred(Container $source, Container $target, ContainerItem $item): void
    {
        event(new AuditableEvent($item, 'container.item.transferred', metadata: [
            'source_container_id' => $source->id,
            'target_container_id' => $target->id,
        ]));

        app(WebhookService::class)->dispatch('container.item.transferred', [
            'source_container' => ContainerData::fromModel($source)->toArray(),
            'target_container' => ContainerData::fromModel($target)->toArray(),
            'container_item' => ContainerItemData::fromModel($item)->toArray(),
            'source_container_id' => $source->id,
            'source_container_uuid' => $source->uuid,
            'target_container_id' => $target->id,
            'target_container_uuid' => $target->uuid,
            'serialised_item_id' => $item->serialised_item_id,
            'product_id' => $item->product_id,
            'user_id' => Auth::id(),
        ]);
    }

    public function nested(Container $parent, Container $child, int $depth): void
    {
        event(new AuditableEvent($child, 'container.nested', metadata: [
            'parent_container_id' => $parent->id,
            'depth' => $depth,
        ]));

        app(WebhookService::class)->dispatch('container.nested', [
            'parent_container' => ContainerData::fromModel($parent)->toArray(),
            'child_container' => ContainerData::fromModel($child)->toArray(),
            'parent_container_id' => $parent->id,
            'parent_container_uuid' => $parent->uuid,
            'child_container_id' => $child->id,
            'child_container_uuid' => $child->uuid,
            'depth' => $depth,
            'user_id' => Auth::id(),
        ]);
    }
}
