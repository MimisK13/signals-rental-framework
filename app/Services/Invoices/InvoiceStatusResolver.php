<?php

namespace App\Services\Invoices;

use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Config-driven effective invoice status from payment totals and due date.
 *
 * Overdue is derived at read time and is never persisted as a stored status.
 */
class InvoiceStatusResolver
{
    /**
     * Resolve the effective status for an invoice snapshot.
     */
    public function resolve(
        string $storedStatus,
        int $amountPaid,
        int $total,
        int $amountOutstanding,
        ?CarbonInterface $dueAt,
        ?CarbonInterface $now = null,
    ): string {
        $now ??= now('UTC');

        /** @var list<array<string, mixed>> $rules */
        $rules = config('invoices.status_resolution', []);

        foreach ($rules as $rule) {
            if ($this->matches($rule, $storedStatus, $amountPaid, $total, $amountOutstanding, $dueAt, $now)) {
                $status = (string) ($rule['status'] ?? '');

                if ($status === '') {
                    throw new InvalidArgumentException('Invoice status resolution rule is missing a status key.');
                }

                return $status;
            }
        }

        return $storedStatus;
    }

    public function isOverdue(
        string $storedStatus,
        int $amountOutstanding,
        ?CarbonInterface $dueAt,
        ?CarbonInterface $now = null,
    ): bool {
        if ($amountOutstanding <= 0 || $dueAt === null) {
            return false;
        }

        $now ??= now('UTC');

        if (! $dueAt->lt($now)) {
            return false;
        }

        return in_array($storedStatus, $this->overdueEligibleStoredStatuses(), true);
    }

    /**
     * @return list<string>
     */
    public function overdueEligibleStoredStatuses(): array
    {
        /** @var list<string> */
        return config('invoices.overdue_eligible_stored_statuses', ['issued', 'partially_paid']);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function matches(
        array $rule,
        string $storedStatus,
        int $amountPaid,
        int $total,
        int $amountOutstanding,
        ?CarbonInterface $dueAt,
        CarbonInterface $now,
    ): bool {
        $when = $rule['when'] ?? null;

        if (! is_array($when)) {
            return false;
        }

        foreach ($when as $field => $expected) {
            $actual = match ($field) {
                'stored_status' => $storedStatus,
                'amount_paid' => $amountPaid,
                'total' => $total,
                'amount_outstanding' => $amountOutstanding,
                'due_at' => $dueAt,
                'now' => $now,
                default => null,
            };

            if (! $this->matchesCondition($actual, $expected, $now)) {
                return false;
            }
        }

        return true;
    }

    private function matchesCondition(mixed $actual, mixed $expected, CarbonInterface $now): bool
    {
        if (is_array($expected)) {
            if (array_key_exists('eq', $expected)) {
                return $actual === $expected['eq'];
            }

            if (array_key_exists('in', $expected)) {
                return is_array($expected['in']) && in_array($actual, $expected['in'], true);
            }

            if (array_key_exists('gt', $expected)) {
                return is_numeric($actual) && $actual > $expected['gt'];
            }

            if (array_key_exists('gte', $expected)) {
                return is_numeric($actual) && $actual >= $expected['gte'];
            }

            if (array_key_exists('lt', $expected)) {
                return is_numeric($actual) && $actual < $expected['lt'];
            }

            if (array_key_exists('lte', $expected)) {
                return is_numeric($actual) && $actual <= $expected['lte'];
            }

            if (array_key_exists('past', $expected)) {
                if (! $expected['past'] || ! $actual instanceof CarbonInterface) {
                    return false;
                }

                return $actual->lt($now);
            }

            if (array_key_exists('blank', $expected)) {
                return $expected['blank'] ? ($actual === null || $actual === '') : ($actual !== null && $actual !== '');
            }

            return false;
        }

        return $actual === $expected;
    }
}
