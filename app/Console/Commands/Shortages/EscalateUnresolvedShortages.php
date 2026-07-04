<?php

namespace App\Console\Commands\Shortages;

use App\Enums\OpportunityState;
use App\Events\AuditableEvent;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Services\Shortages\ShortageDetector;
use App\ValueObjects\Shortage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Symfony\Component\Console\Attribute\AsCommand;
use Thunk\Verbs\Facades\Verbs;

/**
 * Escalates unresolved shortages on confirmed orders approaching dispatch
 * (shortage-resolution-sub-hires.md §9.1 `shortage.escalated`).
 *
 * Idempotent via the projection-only `shortage_escalated_at` marker on each line
 * item — cleared when the shortage ceases to exist so a re-detected shortage can
 * escalate again.
 */
#[AsCommand(name: 'shortages:escalate-unresolved')]
class EscalateUnresolvedShortages extends Command
{
    protected $signature = 'shortages:escalate-unresolved';

    protected $description = 'Notify when confirmed-order shortages approach dispatch without resolution';

    public function handle(ShortageDetector $detector): int
    {
        if (Verbs::isReplaying()) {
            $this->warn('Skipping shortage escalation during Verbs replay.');

            return self::SUCCESS;
        }

        $now = Carbon::now('UTC');
        $daysBefore = max(0, (int) settings(
            'shortages.escalation_days_before_dispatch',
            config('shortages.escalation_days_before_dispatch', 3),
        ));
        $thresholdEnd = $now->copy()->addDays($daysBefore);
        $batchSize = max(1, (int) config('shortages.escalation_batch_size', 500));
        $processed = 0;

        Opportunity::query()
            ->where('state', OpportunityState::Order->value)
            ->where('starts_at', '<=', $thresholdEnd)
            ->where('starts_at', '>=', $now)
            ->orderBy('id')
            ->chunkById(50, function ($opportunities) use ($detector, $now, &$processed, $batchSize): bool {
                foreach ($opportunities as $opportunity) {
                    if ($processed >= $batchSize) {
                        return false;
                    }

                    $opportunity->loadMissing('items');

                    foreach ($opportunity->items as $item) {
                        if ($processed >= $batchSize) {
                            return false;
                        }

                        if ($item->shortage_escalated_at !== null) {
                            continue;
                        }

                        $shortage = $detector->forItem($item, $opportunity);

                        if ($shortage === null || ! $shortage->isUnresolved()) {
                            continue;
                        }

                        $this->escalate($opportunity, $item, $shortage, $now);
                        $processed++;
                    }
                }

                return true;
            });

        $this->info(sprintf('Escalated %d shortage line(s).', $processed));

        return self::SUCCESS;
    }

    private function escalate(Opportunity $opportunity, OpportunityItem $item, Shortage $shortage, Carbon $now): void
    {
        event(new AuditableEvent(
            model: $opportunity,
            action: 'shortage.escalated',
            newValues: $shortage->toSnapshot(),
            metadata: [
                'opportunity_item_id' => $item->id,
                'escalation_reason' => 'dispatch_window_approaching',
                'days_before_dispatch' => (int) settings(
                    'shortages.escalation_days_before_dispatch',
                    config('shortages.escalation_days_before_dispatch', 3),
                ),
            ],
        ));

        $item->forceFill(['shortage_escalated_at' => $now])->saveQuietly();
    }
}
