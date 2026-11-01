<?php

namespace App\Services\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Models\Opportunity;
use App\Services\Notifications\EventNotificationMapRegistry;
use Closure;
use LogicException;
use Thunk\Verbs\Facades\Verbs;

class OpportunityTotalsBatch
{
    use CommitsVerbsEvents;

    private ?int $opportunityId = null;

    private bool $pending = false;

    /**
     * Keep line pricing and per-event audits immediate, then roll up the parent
     * once before committing the complete mutation batch.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @param  list<string>  $auditActions  All audit actions emitted by the callback.
     * @return TReturn
     */
    public function run(Opportunity $opportunity, Closure $callback, array $auditActions = []): mixed
    {
        if ($this->opportunityId !== null) {
            throw new LogicException('Opportunity totals batches cannot be nested.');
        }

        // Notification mappings may inspect intermediate parent totals. Keep
        // their original immediate-rollup behavior, including plugin mappings.
        $notificationMap = app(EventNotificationMapRegistry::class);

        foreach ($auditActions as $action) {
            if ($notificationMap->has($action)) {
                return $this->commitVerbs($callback);
            }
        }

        return $this->commitVerbs(function () use ($opportunity, $callback): mixed {
            $this->opportunityId = (int) $opportunity->id;
            $this->pending = false;

            try {
                $result = $callback();
                Verbs::commit();
                $pending = $this->pending;
            } finally {
                $this->opportunityId = null;
                $this->pending = false;
            }

            if ($pending) {
                app(OpportunityTotalsCalculator::class)->rollUp($opportunity->fresh());
            }

            return $result;
        });
    }

    public function deferRollUp(Opportunity $opportunity): bool
    {
        if ($this->opportunityId !== (int) $opportunity->id) {
            return false;
        }

        $this->pending = true;

        return true;
    }
}
