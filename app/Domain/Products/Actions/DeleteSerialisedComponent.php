<?php

declare(strict_types=1);

namespace App\Domain\Products\Actions;

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\SerialisedComponent;
use App\Domain\Webhooks\Services\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Remove a component line from a product's kit composition.
 *
 * Maintains the denormalised `products.is_kit` flag — clearing it to false when
 * the last component is removed so the product reverts to a plain (non-kit)
 * product. The delete and the flag update run in one transaction.
 */
class DeleteSerialisedComponent
{
    public function __invoke(SerialisedComponent $component): void
    {
        Gate::authorize('kits.manage');

        $productId = $component->product_id;

        DB::transaction(function () use ($component, $productId): void {
            $component->delete();

            $remaining = SerialisedComponent::query()
                ->where('product_id', $productId)
                ->exists();

            if (! $remaining) {
                Product::query()
                    ->whereKey($productId)
                    ->update(['is_kit' => false]);
            }
        });

        event(new AuditableEvent($component, 'serialised_component.deleted'));

        app(WebhookService::class)->dispatch('product.updated', [
            'product_id' => $productId,
        ]);
    }
}
