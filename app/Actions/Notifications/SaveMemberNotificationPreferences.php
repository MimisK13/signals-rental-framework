<?php

namespace App\Actions\Notifications;

use App\Data\Notifications\SaveMemberNotificationPreferencesData;
use App\Models\NotificationPreference;
use App\Models\NotificationType;
use App\Models\User;
use App\Services\Notifications\MemberNotificationPreferenceCatalog;
use Illuminate\Validation\ValidationException;

class SaveMemberNotificationPreferences
{
    public function __construct(
        private readonly MemberNotificationPreferenceCatalog $catalog,
    ) {}

    public function __invoke(User $user, SaveMemberNotificationPreferencesData $data): void
    {
        if ($data->targetUserId !== $user->id) {
            abort(403);
        }

        $type = NotificationType::query()
            ->with('setting')
            ->whereKey($data->notificationTypeId)
            ->firstOrFail();

        $tenantChannels = $this->catalog->tenantEnabledChannels($type);

        if ($data->isMuted) {
            NotificationPreference::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'notification_type_id' => $type->id,
                ],
                [
                    'is_muted' => true,
                    'channels' => null,
                ],
            );

            return;
        }

        $requested = $data->channels ?? [];

        foreach ($requested as $channel) {
            if (! in_array($channel, $tenantChannels, true)) {
                throw ValidationException::withMessages([
                    'channels' => ["Channel [{$channel}] is not enabled for this notification type."],
                ]);
            }
        }

        $channels = array_values(array_intersect($tenantChannels, $requested));

        NotificationPreference::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'notification_type_id' => $type->id,
            ],
            [
                'is_muted' => false,
                'channels' => $channels === $tenantChannels ? null : $channels,
            ],
        );
    }
}
