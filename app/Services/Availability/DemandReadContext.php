<?php

namespace App\Services\Availability;

use App\Models\Demand;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Explicit raw-demand context for one read-only availability workflow.
 *
 * Each consumer receives a fresh collection wrapper, so item-specific
 * self-demand exclusion cannot remove rows from the stored raw fact.
 */
final class DemandReadContext
{
    /** @var array<string, Collection<int, Demand>> */
    private array $facts = [];

    /**
     * @param  Closure(): Collection<int, Demand>  $loader
     * @return Collection<int, Demand>
     */
    public function resolve(
        int $productId,
        int $storeId,
        Carbon $from,
        Carbon $to,
        Closure $loader,
    ): Collection {
        $key = $this->key($productId, $storeId, $from, $to);

        if (! array_key_exists($key, $this->facts)) {
            $this->facts[$key] = $loader();
        }

        return new Collection($this->facts[$key]->all());
    }

    private function key(int $productId, int $storeId, Carbon $from, Carbon $to): string
    {
        return implode('|', [
            'active-overlap',
            $productId,
            $storeId,
            $from->getTimestamp(),
            $to->getTimestamp(),
        ]);
    }
}
