<?php

namespace App\Services\VirtualStock;

use InvalidArgumentException;

/**
 * Config-driven lifecycle for virtual serialised stock_levels rows.
 */
class VirtualAssetStatusRegistry
{
    public function defaultStatus(): string
    {
        return (string) config('virtual_stock.virtual_asset_default_status', 'available');
    }

    /**
     * @return array<string, array{label: string, terminal: bool, supplies_availability: bool, badge_class?: string}>
     */
    public function statuses(): array
    {
        /** @var array<string, array{label: string, terminal: bool, supplies_availability: bool, badge_class?: string}> */
        return config('virtual_stock.virtual_asset_statuses', []);
    }

    /**
     * @return array<string, list<string>>
     */
    public function transitions(): array
    {
        /** @var array<string, list<string>> */
        return config('virtual_stock.virtual_asset_transitions', []);
    }

    public function has(string $status): bool
    {
        return array_key_exists($status, $this->statuses());
    }

    public function isTerminal(string $status): bool
    {
        return (bool) ($this->statuses()[$status]['terminal'] ?? false);
    }

    public function suppliesAvailability(string $status): bool
    {
        return (bool) ($this->statuses()[$status]['supplies_availability'] ?? false);
    }

    public function canTransitionTo(string $from, string $to): bool
    {
        if (! $this->has($from) || ! $this->has($to)) {
            return false;
        }

        $allowed = $this->transitions()[$from] ?? [];

        return in_array($to, $allowed, true);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function assertCanTransitionTo(string $from, string $to): void
    {
        if ($from === $to) {
            return;
        }

        if (! $this->canTransitionTo($from, $to)) {
            throw new InvalidArgumentException(
                "Virtual asset status cannot transition from [{$from}] to [{$to}].",
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function assertKnown(string $status): void
    {
        if (! $this->has($status)) {
            throw new InvalidArgumentException("Unknown virtual asset status [{$status}].");
        }
    }
}
