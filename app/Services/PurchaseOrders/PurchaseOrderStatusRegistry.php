<?php

namespace App\Services\PurchaseOrders;

use InvalidArgumentException;

/**
 * Config-driven purchase order status lifecycle.
 */
class PurchaseOrderStatusRegistry
{
    public function defaultStatus(): string
    {
        return (string) config('purchase_orders.default_status', 'draft');
    }

    /**
     * @return array<string, array{label: string, terminal: bool, badge_class?: string}>
     */
    public function statuses(): array
    {
        /** @var array<string, array{label: string, terminal: bool, badge_class?: string}> */
        return config('purchase_orders.statuses', []);
    }

    /**
     * @return array<string, list<string>>
     */
    public function transitions(): array
    {
        /** @var array<string, list<string>> */
        return config('purchase_orders.transitions', []);
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
                "Purchase order status cannot transition from [{$from}] to [{$to}].",
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function assertKnown(string $status): void
    {
        if (! $this->has($status)) {
            throw new InvalidArgumentException("Unknown purchase order status [{$status}].");
        }
    }
}
