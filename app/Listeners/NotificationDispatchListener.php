<?php

namespace App\Listeners;

use App\Actions\Notifications\DispatchNotification;
use App\Data\Notifications\DispatchNotificationData;
use App\Events\AuditableEvent;
use App\Models\Invoice;
use App\Models\NotificationType;
use App\Models\Opportunity;
use App\Models\ShortageResolution;
use App\Models\ShortageWaitlistMonitor;
use App\Models\User;
use App\Models\VirtualStockIntake;
use App\Notifications\DomainEventNotification;
use App\Services\Notifications\EventNotificationConditionEvaluator;
use App\Services\Notifications\EventNotificationMapRegistry;
use App\Services\Notifications\InAppNotificationContentBuilder;
use Thunk\Verbs\Facades\Verbs;

/**
 * Bridges the audit event stream onto the P24 notification dispatch pipeline.
 *
 * Every committed opportunity Verbs mutation, plus shortage resolution and
 * waitlist lifecycle records, already reach the Laravel bus as an
 * {@see AuditableEvent}. This listener rides that same bridge to resolve
 * notification types from the config-driven {@see EventNotificationMapRegistry}
 * and fan out per-recipient {@see DispatchNotification} jobs.
 *
 * Replay-safety (R10): opportunity-sourced AuditableEvents are dispatched from
 * inside the firing Verbs event's handle(), which Verbs re-runs in Phase::Replay;
 * this listener therefore short-circuits whenever {@see Verbs::isReplaying()} is
 * true. Shortage/waitlist AuditableEvents carry no verb_event_id and are never
 * produced during replay, but the same guard still applies harmlessly.
 *
 * Delivery is queued-only via {@see DispatchNotification} → SendNotification;
 * nothing synchronous blocks the request beyond enqueueing jobs after commit.
 */
class NotificationDispatchListener
{
    public function __construct(
        private readonly EventNotificationMapRegistry $map,
        private readonly EventNotificationConditionEvaluator $evaluator,
        private readonly DispatchNotification $dispatch,
        private readonly InAppNotificationContentBuilder $contentBuilder,
    ) {}

    public function handle(AuditableEvent $event): void
    {
        if (Verbs::isReplaying()) {
            return;
        }

        $entries = $this->map->resolve($event->action);

        if ($entries === []) {
            return;
        }

        foreach ($entries as $entry) {
            if (! $this->evaluator->matches($entry['when'] ?? null, $event)) {
                continue;
            }

            foreach ($this->map->notificationTypesForEntry($entry) as $typeKey) {
                $type = NotificationType::query()->where('key', $typeKey)->first();

                if ($type === null) {
                    continue;
                }

                $content = $this->contentBuilder->build($event, $type);

                ($this->dispatch)(new DispatchNotificationData(
                    notificationTypeKey: $typeKey,
                    notificationClass: DomainEventNotification::class,
                    subject: $event->model,
                    payload: $this->payloadFor($event, $content),
                    actorMemberId: $this->actorMemberId($event->userId),
                ));
            }
        }
    }

    /**
     * @param  array{title: string, body: string}  $content
     * @return array<string, mixed>
     */
    private function payloadFor(AuditableEvent $event, array $content): array
    {
        $payload = [
            'title' => $content['title'],
            'body' => $content['body'],
            'action' => $event->action,
        ];

        if ($event->model instanceof Opportunity) {
            return $payload + [
                'opportunity' => [
                    'id' => $event->model->getKey(),
                    'subject' => $event->model->subject,
                    'reference' => $event->model->reference,
                ],
            ];
        }

        if ($event->model instanceof Invoice) {
            return $payload + [
                'invoice' => [
                    'id' => $event->model->getKey(),
                    'number' => $event->model->number,
                    'total' => $event->model->formatMoneyCost('total'),
                ],
            ];
        }

        if ($event->model instanceof ShortageWaitlistMonitor) {
            $event->model->loadMissing('product');

            return $payload + [
                'shortage' => [
                    'product_name' => $event->model->product?->name,
                    'quantity' => $event->model->quantity_needed,
                ],
            ];
        }

        if ($event->model instanceof ShortageResolution) {
            return $payload + [
                'shortage_resolution' => [
                    'id' => $event->model->getKey(),
                    'quantity_resolved' => $event->model->quantity_resolved,
                ],
            ];
        }

        if ($event->model instanceof VirtualStockIntake) {
            return $payload + [
                'virtual_stock_intake' => [
                    'id' => $event->model->getKey(),
                    'number' => $event->model->number,
                ],
            ];
        }

        return $payload + [
            'subject_type' => $event->model::class,
            'subject_id' => $event->model->getKey(),
        ];
    }

    private function actorMemberId(?int $userId): ?int
    {
        if ($userId === null) {
            return null;
        }

        $memberId = User::query()->whereKey($userId)->value('member_id');

        return $memberId !== null ? (int) $memberId : null;
    }
}
