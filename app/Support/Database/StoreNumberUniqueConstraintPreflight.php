<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pre-flight duplicate detection before adding store-scoped number unique indexes.
 */
class StoreNumberUniqueConstraintPreflight
{
    /**
     * @return list<string>
     */
    public function findDuplicateNumbers(string $table): array
    {
        if ($table === 'invoices') {
            return $this->findInvoiceDuplicateNumbers();
        }

        return DB::table($table)
            ->select('number')
            ->whereNotNull('number')
            ->groupBy('store_id', 'number')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('number')
            ->map(fn (mixed $number): string => (string) $number)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function findInvoiceDuplicateNumbers(): array
    {
        return DB::table('invoices')
            ->select('number')
            ->whereNotNull('number')
            ->groupBy('store_id', 'type', 'number')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('number')
            ->map(fn (mixed $number): string => (string) $number)
            ->values()
            ->all();
    }

    /**
     * @throws RuntimeException
     */
    public function assertNoDuplicates(string $table): void
    {
        $duplicates = $this->findDuplicateNumbers($table);

        if ($duplicates === []) {
            return;
        }

        $constraint = $table === 'invoices'
            ? '(store_id, type, number)'
            : '(store_id, number)';

        throw new RuntimeException(sprintf(
            'Cannot add unique constraint on %s %s: duplicate numbers exist: %s',
            $table,
            $constraint,
            implode(', ', $duplicates),
        ));
    }
}
