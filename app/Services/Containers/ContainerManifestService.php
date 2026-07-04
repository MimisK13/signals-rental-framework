<?php

namespace App\Services\Containers;

use App\Data\Containers\ContainerComplianceData;
use App\Data\Containers\ContainerComplianceSlotData;
use App\Models\Container;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Builds depth-limited container manifests and template compliance reports
 * (serialised-containers.md §"Dispatch Validation" / GET compliance).
 *
 * PostgreSQL uses a recursive CTE; SQLite falls back to bounded iteration so
 * the default test lane still exercises the logic.
 */
class ContainerManifestService
{
    /**
     * Roll up active contents grouped by product, including nested containers
     * up to the configured max depth.
     *
     * @return array<int, int> product_id => quantity
     */
    public function manifestQuantities(Container $container, ?int $maxDepth = null): array
    {
        $maxDepth ??= $this->resolveMaxDepth($container);

        if (DB::connection()->getDriverName() === 'pgsql') {
            return $this->manifestQuantitiesViaCte($container, $maxDepth);
        }

        return $this->manifestQuantitiesIterative($container, $maxDepth);
    }

    public function compliance(Container $container): ContainerComplianceData
    {
        $maxDepth = $this->resolveMaxDepth($container);
        $manifest = $this->manifestQuantities($container, $maxDepth);
        $template = $this->templateFor($container);
        /** @var list<array{product_id: int, quantity: int, binding?: string|null}> $templateSlots */
        $templateSlots = $template['slots'] ?? [];

        $slots = [];
        $expectedProductIds = [];
        $compliant = true;

        foreach ($templateSlots as $slot) {
            $productId = (int) $slot['product_id'];
            $expected = (int) $slot['quantity'];
            $actual = (int) ($manifest[$productId] ?? 0);
            $expectedProductIds[$productId] = true;

            $status = match (true) {
                $actual === $expected => 'ok',
                $actual < $expected => 'under',
                default => 'over',
            };

            if ($status !== 'ok') {
                $compliant = false;
            }

            $slots[] = new ContainerComplianceSlotData(
                product_id: $productId,
                expected_quantity: $expected,
                actual_quantity: $actual,
                status: $status,
            );
        }

        foreach ($manifest as $productId => $quantity) {
            if ($quantity <= 0 || isset($expectedProductIds[$productId])) {
                continue;
            }

            $compliant = false;

            $slots[] = new ContainerComplianceSlotData(
                product_id: (int) $productId,
                expected_quantity: 0,
                actual_quantity: (int) $quantity,
                status: 'unexpected',
            );
        }

        return new ContainerComplianceData(
            container_id: $container->id,
            container_uuid: $container->uuid,
            status: $container->status->value,
            is_sealed: $container->status->value === 'sealed',
            scan_mode: $container->scan_mode->value,
            compliant: $compliant,
            max_nesting_depth: $maxDepth,
            slots: $slots,
            manifest: $manifest,
        );
    }

    /**
     * Compare grouped manifest quantities against the product template for strict
     * seal validation. Returns per-product error messages keyed by product id.
     *
     * @return array<string, list<string>>
     */
    public function strictTemplateViolations(Container $container): array
    {
        $manifest = $this->manifestQuantities($container);
        $template = $this->templateFor($container);
        /** @var list<array{product_id: int, quantity: int}> $templateSlots */
        $templateSlots = $template['slots'] ?? [];

        if ($templateSlots === []) {
            return [];
        }

        $errors = [];
        $expectedProductIds = [];

        foreach ($templateSlots as $slot) {
            $productId = (int) $slot['product_id'];
            $expected = (int) $slot['quantity'];
            $actual = (int) ($manifest[$productId] ?? 0);
            $expectedProductIds[$productId] = true;

            if ($actual === $expected) {
                continue;
            }

            $productName = Product::query()->whereKey($productId)->value('name') ?? (string) $productId;

            if ($actual < $expected) {
                $errors["products.{$productId}"] = [
                    __(':product is under-filled: expected :expected, found :actual.', [
                        'product' => $productName,
                        'expected' => $expected,
                        'actual' => $actual,
                    ]),
                ];
            } else {
                $errors["products.{$productId}"] = [
                    __(':product is over-filled: expected :expected, found :actual.', [
                        'product' => $productName,
                        'expected' => $expected,
                        'actual' => $actual,
                    ]),
                ];
            }
        }

        foreach ($manifest as $productId => $quantity) {
            if ($quantity <= 0 || isset($expectedProductIds[$productId])) {
                continue;
            }

            $productName = Product::query()->whereKey($productId)->value('name') ?? (string) $productId;

            $errors["products.{$productId}"] = [
                __('Unexpected product :product: found :actual, not in template.', [
                    'product' => $productName,
                    'actual' => $quantity,
                ]),
            ];
        }

        return $errors;
    }

    /**
     * Ensure every nested container in the tree is sealed before the parent seals.
     *
     * @throws ValidationException
     */
    public function guardNestedContainersSealed(Container $container): void
    {
        $container->loadMissing('activeItems');

        foreach ($container->activeItems as $item) {
            $nested = Container::query()
                ->active()
                ->where('serialised_item_id', $item->serialised_item_id)
                ->first();

            if ($nested === null) {
                continue;
            }

            if ($nested->status->value !== 'sealed') {
                throw ValidationException::withMessages([
                    'container' => __('Nested container :name must be sealed before this container can be sealed.', [
                        'name' => $nested->name,
                    ]),
                    'nested_container_id' => [$nested->uuid],
                ]);
            }

            $this->guardNestedContainersSealed($nested);
        }
    }

    public function resolveMaxDepth(Container $container): int
    {
        $root = $this->resolveRootContainer($container);

        $configured = $root->product?->container_max_nesting_depth;

        if ($configured !== null) {
            return max(1, (int) $configured);
        }

        return max(1, (int) config('containers.default_max_nesting_depth', 2));
    }

    /**
     * The resulting 1-based nesting depth after packing {@see $nested} into
     * {@see $target} (target depth + 1).
     */
    public function nestingDepth(Container $target, Container $nested): int
    {
        return $this->depthOf($target) + 1;
    }

    /**
     * Count of all serialised items inside a container tree (including nested).
     */
    public function recursiveItemCount(Container $container): int
    {
        return array_sum($this->manifestQuantities($container));
    }

    /**
     * Product-quantity manifest for transport dispatch validation — rolled-up contents
     * plus one count per housing asset in the nesting tree.
     *
     * @return array<int, int> product_id => quantity
     */
    public function dispatchProductQuantities(Container $container): array
    {
        $maxDepth = $this->resolveMaxDepth($container);
        $quantities = $this->dispatchContentQuantities($container, $maxDepth);
        $this->accumulateHousingProducts($container, $maxDepth, 1, $quantities);

        return $quantities;
    }

    /**
     * Leaf contents rolled up for dispatch validation — nested container housings
     * are excluded here because {@see accumulateHousingProducts()} counts each
     * housing product once (serialised-containers.md §Step 3).
     *
     * @return array<int, int>
     */
    protected function dispatchContentQuantities(Container $container, int $maxDepth): array
    {
        return $this->collectDispatchContentQuantitiesRecursive($container, $maxDepth, 1);
    }

    /**
     * @return array<int, int>
     */
    protected function collectDispatchContentQuantitiesRecursive(Container $container, int $maxDepth, int $currentDepth): array
    {
        if ($currentDepth > $maxDepth) {
            return [];
        }

        $container->loadMissing('activeItems');
        $quantities = [];

        foreach ($container->activeItems as $item) {
            $nested = Container::query()
                ->active()
                ->where('serialised_item_id', $item->serialised_item_id)
                ->first();

            if ($nested !== null && $currentDepth < $maxDepth) {
                $nestedQuantities = $this->collectDispatchContentQuantitiesRecursive(
                    $nested,
                    $maxDepth,
                    $currentDepth + 1,
                );

                foreach ($nestedQuantities as $nestedProductId => $qty) {
                    $quantities[$nestedProductId] = ($quantities[$nestedProductId] ?? 0) + $qty;
                }

                continue;
            }

            $productId = (int) $item->product_id;
            $quantities[$productId] = ($quantities[$productId] ?? 0) + 1;
        }

        return $quantities;
    }

    /**
     * Full recursive asset manifest for transport dispatch execution — every contained
     * serialised asset and every nested housing asset with its direct container
     * stock level for {@see AssetContainerSet}.
     *
     * @return list<array{serialised_item_id: int, product_id: int, container_stock_level_id: int|null}>
     */
    public function dispatchAssets(Container $container): array
    {
        $assets = [];
        $this->collectDispatchAssets($container, $this->resolveMaxDepth($container), 1, null, $assets);

        return $assets;
    }

    /**
     * @param  array<int, int>  $quantities
     */
    protected function accumulateHousingProducts(
        Container $container,
        int $maxDepth,
        int $currentDepth,
        array &$quantities,
    ): void {
        if ($container->product_id !== null) {
            $productId = (int) $container->product_id;
            $quantities[$productId] = ($quantities[$productId] ?? 0) + 1;
        }

        $container->loadMissing('activeItems');

        foreach ($container->activeItems as $item) {
            $nested = Container::query()
                ->active()
                ->where('serialised_item_id', $item->serialised_item_id)
                ->first();

            if ($nested === null || $currentDepth >= $maxDepth) {
                continue;
            }

            $this->accumulateHousingProducts($nested, $maxDepth, $currentDepth + 1, $quantities);
        }
    }

    /**
     * @param  list<array{serialised_item_id: int, product_id: int, container_stock_level_id: int|null}>  $assets
     */
    protected function collectDispatchAssets(
        Container $container,
        int $maxDepth,
        int $currentDepth,
        ?int $parentHousingStockLevelId,
        array &$assets,
    ): void {
        if ($currentDepth > $maxDepth) {
            return;
        }

        $container->loadMissing(['activeItems', 'product']);

        if ($container->serialised_item_id !== null && $container->product_id !== null) {
            $assets[] = [
                'serialised_item_id' => (int) $container->serialised_item_id,
                'product_id' => (int) $container->product_id,
                'container_stock_level_id' => $parentHousingStockLevelId,
            ];
        }

        $housingStockLevelId = $container->serialised_item_id;

        foreach ($container->activeItems as $item) {
            $nested = Container::query()
                ->active()
                ->where('serialised_item_id', $item->serialised_item_id)
                ->first();

            if ($nested !== null && $currentDepth < $maxDepth) {
                $this->collectDispatchAssets(
                    $nested,
                    $maxDepth,
                    $currentDepth + 1,
                    $housingStockLevelId !== null ? (int) $housingStockLevelId : null,
                    $assets,
                );

                continue;
            }

            $assets[] = [
                'serialised_item_id' => (int) $item->serialised_item_id,
                'product_id' => (int) $item->product_id,
                'container_stock_level_id' => $housingStockLevelId !== null ? (int) $housingStockLevelId : null,
            ];
        }
    }

    /**
     * All containers in the nesting tree (root first, then nested housings).
     *
     * @return list<Container>
     */
    public function containersInTree(Container $container): array
    {
        $containers = [$container];
        $this->collectNestedContainers($container, $this->resolveMaxDepth($container), 1, $containers);

        return $containers;
    }

    /**
     * @param  list<Container>  $containers
     */
    protected function collectNestedContainers(
        Container $container,
        int $maxDepth,
        int $currentDepth,
        array &$containers,
    ): void {
        if ($currentDepth >= $maxDepth) {
            return;
        }

        $container->loadMissing('activeItems');

        foreach ($container->activeItems as $item) {
            $nested = Container::query()
                ->active()
                ->where('serialised_item_id', $item->serialised_item_id)
                ->first();

            if ($nested === null) {
                continue;
            }

            $containers[] = $nested;
            $this->collectNestedContainers($nested, $maxDepth, $currentDepth + 1, $containers);
        }
    }

    protected function depthOf(Container $container): int
    {
        $depth = 1;
        $current = $container;
        $guard = 0;

        while ($current->parent_container_id !== null && $guard < 10) {
            $parent = Container::query()->find($current->parent_container_id);

            if ($parent === null) {
                break;
            }

            $depth++;
            $current = $parent;
            $guard++;
        }

        return $depth;
    }

    /**
     * @return array{slots?: list<array{product_id: int, quantity: int, binding?: string|null}>, max_items?: int|null}
     */
    protected function templateFor(Container $container): array
    {
        if ($container->product === null) {
            return ['slots' => []];
        }

        return $container->product->container_template ?? ['slots' => []];
    }

    protected function resolveRootContainer(Container $container): Container
    {
        $root = $container;
        $guard = 0;

        while ($root->parent_container_id !== null && $guard < 10) {
            $parent = Container::query()->find($root->parent_container_id);

            if ($parent === null) {
                break;
            }

            $root = $parent;
            $guard++;
        }

        return $root;
    }

    /**
     * @return array<int, int>
     */
    protected function manifestQuantitiesViaCte(Container $container, int $maxDepth): array
    {
        /** @var list<object{product_id: int|string, quantity: int|string}> $rows */
        $rows = DB::select(<<<'SQL'
            WITH RECURSIVE tree AS (
                SELECT
                    ci.product_id,
                    ci.serialised_item_id,
                    1 AS depth
                FROM container_items ci
                WHERE ci.container_id = ?
                  AND ci.unpacked_at IS NULL

                UNION ALL

                SELECT
                    ci.product_id,
                    ci.serialised_item_id,
                    t.depth + 1
                FROM tree t
                INNER JOIN containers nc
                    ON nc.serialised_item_id = t.serialised_item_id
                   AND nc.status <> 'dissolved'
                INNER JOIN container_items ci
                    ON ci.container_id = nc.id
                   AND ci.unpacked_at IS NULL
                WHERE t.depth < ?
            )
            SELECT product_id, COUNT(*)::int AS quantity
            FROM tree
            GROUP BY product_id
        SQL, [$container->id, $maxDepth]);

        $quantities = [];

        foreach ($rows as $row) {
            $quantities[(int) $row->product_id] = (int) $row->quantity;
        }

        return $quantities;
    }

    /**
     * @return array<int, int>
     */
    protected function manifestQuantitiesIterative(Container $container, int $maxDepth): array
    {
        return $this->collectQuantitiesRecursive($container, $maxDepth, 1);
    }

    /**
     * @return array<int, int>
     */
    protected function collectQuantitiesRecursive(Container $container, int $maxDepth, int $currentDepth): array
    {
        if ($currentDepth > $maxDepth) {
            return [];
        }

        $container->loadMissing('activeItems');
        $quantities = [];

        foreach ($container->activeItems as $item) {
            $productId = (int) $item->product_id;
            $quantities[$productId] = ($quantities[$productId] ?? 0) + 1;

            $nested = Container::query()
                ->active()
                ->where('serialised_item_id', $item->serialised_item_id)
                ->first();

            if ($nested === null || $currentDepth >= $maxDepth) {
                continue;
            }

            $nestedQuantities = $this->collectQuantitiesRecursive($nested, $maxDepth, $currentDepth + 1);

            foreach ($nestedQuantities as $nestedProductId => $qty) {
                $quantities[$nestedProductId] = ($quantities[$nestedProductId] ?? 0) + $qty;
            }
        }

        return $quantities;
    }
}
