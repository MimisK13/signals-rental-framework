<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Data\DispatchNotificationData;
use App\Domain\Notifications\Data\NotificationContextData;
use App\Domain\Notifications\Jobs\SendNotification;
use App\Domain\Notifications\Models\NotificationType;
use App\Domain\Notifications\Services\NotificationChannelResolver;
use App\Domain\Notifications\Services\RecipientResolverRegistry;

/**
 * Resolve recipients and fan out per-recipient SendNotification jobs (P24).
 */
class DispatchNotification
{
    public function __construct(
        private readonly RecipientResolverRegistry $recipientResolvers,
        private readonly NotificationChannelResolver $channelResolver,
    ) {}

    public function __invoke(DispatchNotificationData $data): void
    {
        $type = NotificationType::query()
            ->with('setting')
            ->where('key', $data->notificationTypeKey)
            ->first();

        if ($type === null || ! $type->is_active) {
            return;
        }

        $actor = $data->actorMemberId !== null
            ? Member::query()->find($data->actorMemberId)
            : null;

        $context = new NotificationContextData(
            type: $type,
            subject: $data->subject,
            actor: $actor,
        );

        $rules = $data->recipientRules
            ?? ($type->setting !== null ? $type->setting->recipient_rules : null)
            ?? $type->default_recipients
            ?? [];

        if ($rules === []) {
            return;
        }

        $recipients = $this->recipientResolvers->resolveAll($rules, $context);

        foreach ($recipients as $recipient) {
            $logicalChannels = $this->channelResolver->resolve($type, $recipient);

            if ($logicalChannels === []) {
                continue;
            }

            SendNotification::dispatch(
                notificationClass: $data->notificationClass,
                notificationTypeKey: $data->notificationTypeKey,
                recipientId: $recipient->id,
                subjectType: $data->subject::class,
                subjectId: $data->subject->getKey(),
                payload: $data->payload,
                logicalChannels: $logicalChannels,
            );
        }
    }
}
