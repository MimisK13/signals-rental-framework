<?php

namespace App\Listeners\Availability;

use App\Enums\WaitlistMonitorStatus;
use App\Events\Availability\AvailabilityChanged;
use App\Models\ShortageWaitlistMonitor;
use App\Services\AvailabilityService;
use App\Services\Shortages\ShortageEventRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Thunk\Verbs\Facades\Verbs;

/**
 * Waitlist matching (shortage-resolution-sub-hires.md §4.6).
 *
 * Listens for {@see AvailabilityChanged} and, for the changed product/store,
 * checks every ACTIVE {@see ShortageWaitlistMonitor}: when the availability engine
 * now reports enough free stock over the monitor's window to satisfy its
 * `quantity_needed`, the monitor flips to Matched and `shortage.waitlist.matched`
 * fires.
 *
 * Notification on match: `shortage.waitlist.matched` is mapped in
 * {@see config/notifications.php} and dispatched by
 * {@see App\Listeners\NotificationDispatchListener} through the P24 pipeline.
 *
 * Replay-safe: {@see AvailabilityChanged} is never dispatched during replay, and
 * this listener short-circuits on replay as a guard.
 */
class MatchWaitlistMonitors implements ShouldQueue
{
    public function viaQueue(): string
    {
        return (string) config('availability.recalc.queue', 'availability');
    }

    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly ShortageEventRecorder $events,
    ) {}

    public function handle(AvailabilityChanged $event): void
    {
        if (Verbs::isReplaying()) {
            return;
        }

        ShortageWaitlistMonitor::query()
            ->active()
            ->where('product_id', $event->productId)
            ->where('store_id', $event->storeId)
            ->get()
            ->each(function (ShortageWaitlistMonitor $monitor): void {
                $from = $monitor->starts_at ?? Carbon::now();
                $to = $monitor->ends_at ?? $from->copy()->addDay();

                // checkAvailability() requires a mutable Illuminate\Support\Carbon;
                // the cast date attributes are CarbonImmutable, so normalise via
                // Carbon::instance() (no string round-trip).
                $satisfied = $this->availability->checkAvailability(
                    $monitor->product_id,
                    $monitor->store_id,
                    Carbon::instance($from),
                    Carbon::instance($to),
                    $monitor->quantity_needed,
                );

                if (! $satisfied) {
                    return;
                }

                $monitor->update([
                    'status' => WaitlistMonitorStatus::Matched->value,
                    'matched_at' => Carbon::now(),
                ]);

                // update() already synced the in-memory attributes; no fresh() reload.
                $this->events->waitlistMatched($monitor, [
                    'quantity_needed' => $monitor->quantity_needed,
                ]);
            });
    }
}
