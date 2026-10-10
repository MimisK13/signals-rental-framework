<?php

namespace App\Listeners;

use App\Events\AuditableEvent;
use App\Models\ActionLog;

class LogAction
{
    /**
     * Persist an audit row for the given auditable event.
     *
     * Explicitly attributed audit carries an actor and/or occurrence time from
     * the command/event boundary, so the recorded user and time survive replay
     * even when no live auth()/request() context exists. Event-sourced rows are
     * written with firstOrCreate keyed on verb_event_id so replay re-dispatch
     * is a no-op rather than a duplicate insert.
     *
     * Explicit non event-sourced attribution still uses the best-effort legacy
     * failure handling because those callers do not own an enclosing event-store
     * transaction. It never falls back to the live actor or insert time.
     *
     * Legacy events without explicit attribution keep the original behaviour:
     * live auth()->id() plus console-guarded request context, inserted with
     * create() so no verb_event_id key is set.
     *
     * Failure handling differs by branch on purpose. The event-sourced audit
     * runs inside the firing event's handle(), which executes within the
     * commitVerbs() DB::transaction; swallowing a failed audit insert there
     * would break the all-or-nothing guarantee (and can leave a PG transaction
     * aborted), so its exceptions PROPAGATE to roll the whole commit back. The
     * legacy live-auth branch keeps the original swallow-and-report behaviour so
     * a best-effort audit can never break an otherwise-successful operation.
     */
    public function handle(AuditableEvent $event): void
    {
        if ($event->verbEventId !== null) {
            // Event-sourced branch: let ALL failures propagate so the surrounding
            // commitVerbs() transaction rolls back the event row AND the
            // projection. No try/catch here — a swallowed failure would break the
            // all-or-nothing guarantee.
            $attributes = $this->attributes($event) + [
                'user_id' => $event->userId,
                'ip_address' => $event->ipAddress,
                'user_agent' => $event->userAgent,
            ];

            ActionLog::firstOrCreate(
                ['verb_event_id' => $event->verbEventId],
                $attributes,
            );

            return;
        }

        if ($event->occurredAt !== null) {
            if ($event->failOnAuditFailure) {
                ActionLog::create($this->attributes($event) + [
                    'user_id' => $event->userId,
                    'ip_address' => $event->ipAddress,
                    'user_agent' => $event->userAgent,
                ]);

                return;
            }

            try {
                ActionLog::create($this->attributes($event) + [
                    'user_id' => $event->userId,
                    'ip_address' => $event->ipAddress,
                    'user_agent' => $event->userAgent,
                ]);
            } catch (\Throwable $e) {
                report($e);
            }

            return;
        }

        // Legacy live-auth branch: best-effort audit. The full body (including
        // attribute resolution) is guarded so a failed audit can never break an
        // otherwise-successful operation — the original behaviour, preserved.
        try {
            $request = app()->runningInConsole() ? null : request();

            ActionLog::create($this->attributes($event) + [
                'user_id' => auth()->id(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * The shared, actor-independent audit attributes for the event.
     *
     * @return array<string, mixed>
     */
    private function attributes(AuditableEvent $event): array
    {
        $attributes = [
            'action' => $event->action,
            'auditable_type' => $event->model->getMorphClass(),
            'auditable_id' => $event->model->getKey(),
            'old_values' => $event->oldValues,
            'new_values' => $event->newValues,
            'metadata' => $event->metadata,
        ];

        if ($event->occurredAt !== null) {
            $attributes['created_at'] = $event->occurredAt;
        }

        return $attributes;
    }
}
