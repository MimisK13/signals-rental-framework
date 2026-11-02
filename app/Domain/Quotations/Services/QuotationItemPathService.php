<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Services;

use App\Domain\Quotations\Enums\QuotationItemType;
use InvalidArgumentException;

final class QuotationItemPathService
{
    private const int SEGMENT_WIDTH = 4;

    /**
     * @param  array<int, array{id: int, depth: int}>  $nodes
     * @return array<int, string>
     */
    public function rebuild(array $nodes): array
    {
        $counters = [];
        $prefixAtDepth = [];
        $previousDepth = 0;
        $paths = [];

        foreach ($nodes as $node) {
            $id = (int) $node['id'];
            $depth = $this->clampDepth((int) $node['depth'], $previousDepth);
            $this->resetDeeperThan($counters, $depth);
            $counters[$depth] = ($counters[$depth] ?? 0) + 1;
            $parentPrefix = $depth > 1 ? ($prefixAtDepth[$depth - 1] ?? '') : '';
            $path = $parentPrefix.str_pad((string) $counters[$depth], self::SEGMENT_WIDTH, '0', STR_PAD_LEFT);
            $prefixAtDepth[$depth] = $path;
            $this->resetDeeperThan($prefixAtDepth, $depth);
            $paths[$id] = $path;
            $previousDepth = $depth;
        }

        return $paths;
    }

    /** @param array<int, array{id: int, depth: int, item_type: string}> $nodes */
    public function validatePlacement(array $nodes): void
    {
        $previousDepth = 0;
        $typeAtDepth = [];

        foreach ($nodes as $node) {
            $id = (int) $node['id'];
            $depth = $this->clampDepth((int) $node['depth'], $previousDepth);
            $type = QuotationItemType::from((string) $node['item_type']);
            $parentType = $depth > 1 ? ($typeAtDepth[$depth - 1] ?? null) : null;

            if ($type === QuotationItemType::Accessory && $parentType !== QuotationItemType::Product) {
                throw new InvalidArgumentException("Item {$id} (accessory) must be placed under a product.");
            }

            if ($type !== QuotationItemType::Accessory && $parentType !== null && $parentType !== QuotationItemType::Group) {
                throw new InvalidArgumentException("Item {$id} ({$type->value}) must be placed at root or under a group.");
            }

            $typeAtDepth[$depth] = $type;
            $this->resetDeeperThan($typeAtDepth, $depth);
            $previousDepth = $depth;
        }
    }

    private function clampDepth(int $depth, int $previousDepth): int
    {
        return max(1, min($depth, $previousDepth + 1));
    }

    /** @param array<int, mixed> $map */
    private function resetDeeperThan(array &$map, int $depth): void
    {
        foreach (array_keys($map) as $key) {
            if ($key > $depth) {
                unset($map[$key]);
            }
        }
    }
}
