<?php

namespace App\Services\VirtualStock;

use InvalidArgumentException;

/**
 * Config-driven virtual stock intake status lifecycle.
 */
class VirtualStockStatusRegistry
{
    public function defaultStatus(): string
    {
        return (string) config('virtual_stock.default_status', 'draft');
    }

    /**
     * @return array<string, array{label: string, terminal: bool, badge_class?: string}>
     */
    public function statuses(): array
    {
        /** @var array<string, array{label: string, terminal: bool, badge_class?: string}> */
        return config('virtual_stock.statuses', []);
    }

    /**
     * @return array<string, list<string>>
     */
    public function transitions(): array
    {
        /** @var array<string, list<string>> */
        return config('virtual_stock.transitions', []);
    }

    public function has(string $status): bool
    {
        return array_key_exists($status, $this->statuses());
    }

    public function isTerminal(string $status): bool
    {
        return (bool) ($this->statuses()[$status]['terminal'] ?? false);
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
                "Virtual stock intake status cannot transition from [{$from}] to [{$to}].",
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function assertKnown(string $status): void
    {
        if (! $this->has($status)) {
            throw new InvalidArgumentException("Unknown virtual stock intake status [{$status}].");
        }
    }
}
