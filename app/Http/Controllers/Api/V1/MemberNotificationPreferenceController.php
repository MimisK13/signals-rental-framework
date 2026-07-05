<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Notifications\SaveNotificationPreferences;
use App\Data\Notifications\NotificationPreferenceData;
use App\Data\Notifications\SaveNotificationPreferenceItemData;
use App\Data\Notifications\SaveNotificationPreferencesData;
use App\Http\Controllers\Api\Controller;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\User;
use App\Services\Notifications\MemberNotificationPreferenceCatalog;
use App\Support\Api\Attributes\PriceWeight;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MemberNotificationPreferenceController extends Controller
{
    /**
     * List notification preferences for a member's linked user account.
     *
     * @response array{notification_preferences: list<NotificationPreferenceData>}
     */
    #[ApiResponse(200, 'Member notification preferences', type: 'array{notification_preferences: list<array<string, mixed>>}')]
    #[PriceWeight(0)]
    public function index(Member $member, MemberNotificationPreferenceCatalog $catalog): JsonResponse
    {
        $this->authorizeApi('members.view', 'notification-preferences:read');

        $user = $this->resolveMemberUser($member);

        $preferences = $catalog->forUser($user)
            ->map(fn (array $row): array => NotificationPreferenceData::fromCatalogRow($row)->toArray())
            ->values()
            ->all();

        return $this->respondWithCollection($preferences, 'notification_preferences');
    }

    /**
     * Bulk update notification preferences for a member's linked user account.
     *
     * @response array{notification_preferences: list<NotificationPreferenceData>}
     */
    #[ApiResponse(200, 'Updated member notification preferences', type: 'array{notification_preferences: list<array<string, mixed>>}')]
    #[PriceWeight(1)]
    public function update(
        Request $request,
        Member $member,
        SaveNotificationPreferences $saveNotificationPreferences,
        MemberNotificationPreferenceCatalog $catalog,
    ): JsonResponse {
        $this->authorizeApi('members.edit', 'notification-preferences:write');

        /** @var User $actor */
        $actor = $request->user();
        $user = $this->resolveMemberUser($member);

        $validated = $request->validate(SaveNotificationPreferencesData::rules());
        $dto = new SaveNotificationPreferencesData(
            preferences: array_map(
                fn (array $row): SaveNotificationPreferenceItemData => SaveNotificationPreferenceItemData::from($row),
                $validated['preferences'],
            ),
            target_user_id: $user->id,
        );

        $saveNotificationPreferences($actor, $dto);

        $preferences = $catalog->forUser($user)
            ->map(fn (array $row): array => NotificationPreferenceData::fromCatalogRow($row)->toArray())
            ->values()
            ->all();

        return $this->respondWithCollection($preferences, 'notification_preferences');
    }

    private function resolveMemberUser(Member $member): User
    {
        $this->assertUserCanAccessMember($member);

        $user = $member->user;

        if ($user === null) {
            abort(Response::HTTP_NOT_FOUND, 'Member does not have a linked user account.');
        }

        return $user;
    }

    private function assertUserCanAccessMember(Member $member): void
    {
        /** @var User|null $user */
        $user = auth()->user();

        if ($user === null) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $storeIds = $user->accessibleStoreIds();

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
