<?php

namespace App\Actions\Notifications;

use App\Data\Notifications\SaveMemberNotificationPreferencesData;
use App\Data\Notifications\SaveNotificationPreferencesData;
use App\Models\User;

class SaveNotificationPreferences
{
    public function __construct(
        private readonly SaveMemberNotificationPreferences $saveMemberNotificationPreferences,
    ) {}

    public function __invoke(User $actor, SaveNotificationPreferencesData $data): void
    {
        $targetUserId = $data->target_user_id ?? $actor->id;

        foreach ($data->preferences as $item) {
            ($this->saveMemberNotificationPreferences)($actor, new SaveMemberNotificationPreferencesData(
                targetUserId: $targetUserId,
                notificationTypeId: $item->notification_type_id,
                channels: $item->is_muted ? null : $item->channels,
                isMuted: $item->is_muted,
            ));
        }
    }
}
