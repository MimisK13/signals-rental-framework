<?php

namespace App\Services\Notifications;

use InvalidArgumentException;

/**
 * Config-driven communication log status lifecycle (P24).
 */
class CommunicationLogStatusRegistry
{
    /**
     * @return array<string, array{label: string, terminal: bool}>
     */
    public function statuses(): array
    {
        /** @var array<string, array{label: string, terminal: bool}> */
        return config('communication_log.statuses', []);
    }

    /**
     * @return array<string, list<string>>
     */
    public function transitions(): array
    {
        /** @var array<string, list<string>> */
        return config('communication_log.transitions', []);
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
                "Communication log status cannot transition from [{$from}] to [{$to}].",
            );
        }
    }
}
