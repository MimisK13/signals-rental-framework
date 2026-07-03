<?php

namespace App\Console\Commands\Invoices;

use App\Events\AuditableEvent;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Symfony\Component\Console\Attribute\AsCommand;
use Thunk\Verbs\Facades\Verbs;

/**
 * Fires side effects for newly-overdue invoices (audit, webhooks, notifications).
 *
 * Overdue is derived at read time and is never written as a stored status. This
 * command only dispatches `invoice.overdue` once per invoice, tracked via the
 * projection-only `overdue_notified_at` marker.
 */
#[AsCommand(name: 'invoices:process-overdue')]
class ProcessOverdueInvoices extends Command
{
    protected $signature = 'invoices:process-overdue';

    protected $description = 'Dispatch overdue side effects for unpaid invoices past their due date';

    public function handle(): int
    {
        if (Verbs::isReplaying()) {
            $this->warn('Skipping overdue processing during Verbs replay.');

            return self::SUCCESS;
        }

        $now = Carbon::now('UTC');
        $batchSize = max(1, (int) config('invoices.overdue_batch_size', 500));

        $overdue = Invoice::query()
            ->overdue($now)
            ->whereNull('overdue_notified_at')
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        if ($overdue->isEmpty()) {
            $this->info('No newly overdue invoices found.');

            return self::SUCCESS;
        }

        foreach ($overdue as $invoice) {
            event(new AuditableEvent(
                model: $invoice,
                action: 'invoice.overdue',
                newValues: [
                    'due_at' => $invoice->due_at?->toIso8601String(),
                    'amount_outstanding' => $invoice->amount_outstanding,
                    'effective_status' => $invoice->effectiveStatus(),
                ],
            ));

            $invoice->forceFill(['overdue_notified_at' => $now])->saveQuietly();
        }

        $this->info(sprintf('Processed %d newly overdue invoice(s).', $overdue->count()));

        return self::SUCCESS;
    }
}
