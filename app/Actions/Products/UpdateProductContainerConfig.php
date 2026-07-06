<?php

namespace App\Actions\Products;

use App\Data\Products\ProductContainerConfigData;
use App\Data\Products\UpdateProductContainerConfigData;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\StockMethod;
use App\Events\AuditableEvent;
use App\Models\Container;
use App\Models\Product;
use App\Services\Api\WebhookService;
use App\Services\Containers\ContainerTemplateValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateProductContainerConfig
{
    public function __invoke(Product $product, UpdateProductContainerConfigData $data): ProductContainerConfigData
    {
        Gate::authorize('manageTemplates', Container::class);

        if ($product->stock_method !== StockMethod::Serialised) {
            throw ValidationException::withMessages([
                'stock_method' => __('Container configuration is only available for serialised products.'),
            ]);
        }

        $mode = $data->container_availability_mode ?? ContainerAvailabilityMode::Transport;

        if ($data->is_containerable) {
            app(ContainerTemplateValidator::class)->validate($product, $data->container_template, $mode);
        }

        return DB::transaction(function () use ($product, $data, $mode): ProductContainerConfigData {
            $checkinMode = $data->container_checkin_mode;

            if ($mode === ContainerAvailabilityMode::Transport) {
                $checkinMode = null;
            }

            $template = $data->is_containerable ? $this->normaliseTemplate($data->container_template, $mode) : null;

            $product->update([
                'is_containerable' => $data->is_containerable,
                'container_availability_mode' => $data->is_containerable ? $mode->value : null,
                'container_checkin_mode' => $data->is_containerable ? $checkinMode?->value : null,
                'container_max_nesting_depth' => $data->container_max_nesting_depth,
                'container_repack_on_return' => $data->container_repack_on_return,
                'is_warehouse_addable' => $data->is_warehouse_addable,
                'warehouse_add_default_charge' => $data->warehouse_add_default_charge,
                'container_template' => $template,
            ]);

            $product->refresh();

            event(new AuditableEvent($product, 'product.container_config.updated'));

            app(WebhookService::class)->dispatch('product.updated', [
                'product_id' => $product->id,
            ]);

            return ProductContainerConfigData::fromModel($product);
        });
    }

    /**
     * Apply mode-default bindings and compute max_items when omitted.
     *
     * @param  array{slots?: list<array{product_id?: mixed, quantity?: mixed, binding?: mixed}>, max_items?: int|null}|null  $template
     * @return array{slots: list<array{product_id: int, quantity: int, binding: string}>, max_items: int}|null
     */
    private function normaliseTemplate(?array $template, ContainerAvailabilityMode $mode): ?array
    {
        if ($template === null) {
            return null;
        }

        $slots = $template['slots'] ?? [];

        if ($slots === []) {
            return null;
        }

        $normalisedSlots = [];
        $total = 0;

        foreach ($slots as $slot) {
            $quantity = (int) ($slot['quantity'] ?? 0);
            $total += $quantity;

            $binding = match ($mode) {
                ContainerAvailabilityMode::Kit => 'fixed',
                ContainerAvailabilityMode::Transport => 'pool',
                ContainerAvailabilityMode::Hybrid => (string) ($slot['binding'] ?? ''),
            };

            $normalisedSlots[] = [
                'product_id' => (int) $slot['product_id'],
                'quantity' => $quantity,
                'binding' => $binding,
            ];
        }

        return [
            'slots' => $normalisedSlots,
            'max_items' => array_key_exists('max_items', $template) && $template['max_items'] !== null
                ? (int) $template['max_items']
                : $total,
        ];
    }
}
