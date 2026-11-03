<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Presentation\Http\Controllers\Api\V1;

use App\Domain\Notifications\Actions\SaveNotificationPreferences;
use App\Domain\Notifications\Data\NotificationPreferenceData;
use App\Domain\Notifications\Data\SaveNotificationPreferenceItemData;
use App\Domain\Notifications\Data\SaveNotificationPreferencesData;
use App\Domain\Notifications\Services\MemberNotificationPreferenceCatalog;
use App\Domain\API\Presentation\Http\Controllers\Controller;
use App\Models\User;
use App\Domain\API\Support\Attributes\PriceWeight;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    /**
     * List the authenticated user's notification preferences.
     *
     * @response array{notification_preferences: list<NotificationPreferenceData>}
     */
    #[ApiResponse(200, 'Authenticated user notification preferences', type: 'array{notification_preferences: list<array<string, mixed>>}')]
    #[PriceWeight(0)]
    public function index(Request $request, MemberNotificationPreferenceCatalog $catalog): JsonResponse
    {
        $this->authorizeTokenAbility('notification-preferences:read');

        /** @var User $user */
        $user = $request->user();

        $preferences = $catalog->forUser($user)
            ->map(fn (array $row): array => NotificationPreferenceData::fromCatalogRow($row)->toArray())
            ->values()
            ->all();

        return $this->respondWithCollection($preferences, 'notification_preferences');
    }

    /**
     * Bulk update the authenticated user's notification preferences.
     *
     * @response array{notification_preferences: list<NotificationPreferenceData>}
     */
    #[ApiResponse(200, 'Updated notification preferences', type: 'array{notification_preferences: list<array<string, mixed>>}')]
    #[PriceWeight(1)]
    public function update(Request $request, SaveNotificationPreferences $saveNotificationPreferences, MemberNotificationPreferenceCatalog $catalog): JsonResponse
    {
        $this->authorizeTokenAbility('notification-preferences:write');

        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate(SaveNotificationPreferencesData::rules());
        $dto = new SaveNotificationPreferencesData(
            preferences: array_map(
                fn (array $row): SaveNotificationPreferenceItemData => SaveNotificationPreferenceItemData::from($row),
                $validated['preferences'],
            ),
        );

        $saveNotificationPreferences($user, $dto);

        $preferences = $catalog->forUser($user)
            ->map(fn (array $row): array => NotificationPreferenceData::fromCatalogRow($row)->toArray())
            ->values()
            ->all();

        return $this->respondWithCollection($preferences, 'notification_preferences');
    }
}
