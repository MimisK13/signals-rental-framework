<?php

namespace App\Services\Invoices;

use InvalidArgumentException;

/**
 * Config-driven credit note status lifecycle.
 */
class CreditNoteStatusRegistry
{
    public function defaultStatus(): string
    {
        return (string) config('credit_notes.default_status', 'issued');
    }

    /**
     * @return array<string, array{label: string, terminal: bool}>
     */
    public function statuses(): array
    {
        /** @var array<string, array{label: string, terminal: bool}> */
        return config('credit_notes.statuses', []);
    }

    public function has(string $status): bool
    {
        return array_key_exists($status, $this->statuses());
    }

    /**
     * @throws InvalidArgumentException
     */
    public function assertKnown(string $status): void
    {
        if (! $this->has($status)) {
            throw new InvalidArgumentException("Unknown credit note status [{$status}].");
        }
    }
}
