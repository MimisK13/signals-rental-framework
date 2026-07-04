<?php

namespace App\Console\Commands\VirtualStock;

use App\Events\AuditableEvent;
use App\Models\VirtualStockIntake;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Symfony\Component\Console\Attribute\AsCommand;
use Thunk\Verbs\Facades\Verbs;

/**
 * Detects virtual stock intakes still on hire past their available_until window
 * (shortage-resolution-sub-hires.md §5.5 / §9.3 `virtual_stock.overrun`).
 *
 * Idempotent via the projection-only `overrun_notified_at` marker — the intake
 * stays in overrun until returned/cancelled, but the notification fires once.
 */
#[AsCommand(name: 'virtual-stock:detect-overruns')]
class DetectVirtualStockOverruns extends Command
{
    protected $signature = 'virtual-stock:detect-overruns';

    protected $description = 'Notify when virtual stock intakes pass available_until without return';

    public function handle(): int
    {
        if (Verbs::isReplaying()) {
            $this->warn('Skipping virtual stock overrun detection during Verbs replay.');

            return self::SUCCESS;
        }

        $now = Carbon::now('UTC');
        $batchSize = max(1, (int) config('shortages.overrun_batch_size', 500));

        $overruns = VirtualStockIntake::query()
            ->whereIn('status', ['confirmed', 'received'])
            ->whereNull('returned_at')
            ->whereNull('cancelled_at')
            ->where('available_until', '<', $now)
            ->whereNull('overrun_notified_at')
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        if ($overruns->isEmpty()) {
            $this->info('No newly overdue virtual stock intakes found.');

            return self::SUCCESS;
        }

        foreach ($overruns as $intake) {
            event(new AuditableEvent(
                model: $intake,
                action: 'virtual_stock.overrun',
                newValues: [
                    'status' => $intake->status,
                    'available_until' => $intake->available_until->toIso8601String(),
                    'quantity' => $intake->quantity,
                    'product_id' => $intake->product_id,
                    'store_id' => $intake->store_id,
                ],
            ));

            $intake->forceFill(['overrun_notified_at' => $now])->saveQuietly();
        }

        $this->info(sprintf('Processed %d virtual stock overrun(s).', $overruns->count()));

        return self::SUCCESS;
    }
}
