<?php

namespace App\Actions\Notifications;

use App\Data\Notifications\SaveMemberNotificationPreferencesData;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\NotificationPreference;
use App\Models\NotificationType;
use App\Models\User;
use App\Services\Notifications\MemberNotificationPreferenceCatalog;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class SaveMemberNotificationPreferences
{
    public function __construct(
        private readonly MemberNotificationPreferenceCatalog $catalog,
    ) {}

    public function __invoke(User $actor, SaveMemberNotificationPreferencesData $data): void
    {
        if ($data->targetUserId !== $actor->id) {
            Gate::authorize('members.edit');
            $this->assertActorCanManageTargetUser($actor, $data->targetUserId);
        }

        $type = NotificationType::query()
            ->with('setting')
            ->whereKey($data->notificationTypeId)
            ->firstOrFail();

        $tenantChannels = $this->catalog->tenantEnabledChannels($type);

        if ($data->isMuted) {
            NotificationPreference::query()->updateOrCreate(
                [
                    'user_id' => $data->targetUserId,
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
                'user_id' => $data->targetUserId,
                'notification_type_id' => $type->id,
            ],
            [
                'is_muted' => false,
                'channels' => $channels === $tenantChannels ? null : $channels,
            ],
        );
    }

    private function assertActorCanManageTargetUser(User $actor, int $targetUserId): void
    {
        $targetUser = User::query()->findOrFail($targetUserId);

        if ($targetUser->member_id === null) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $member = Member::query()->find($targetUser->member_id);

        if ($member === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $storeIds = $actor->accessibleStoreIds();

        if ($storeIds === null) {
            return;
        }

        $memberStoreIds = MemberStore::query()
            ->where('member_id', $member->id)
            ->pluck('store_id')
            ->all();

        if ($memberStoreIds === []) {
            return;
        }

        if (array_intersect($storeIds, $memberStoreIds) === []) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }
}
