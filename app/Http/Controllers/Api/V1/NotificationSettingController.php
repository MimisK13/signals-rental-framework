<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Notifications\SaveNotificationSettings;
use App\Data\Notifications\NotificationSettingData;
use App\Data\Notifications\SaveNotificationSettingItemData;
use App\Data\Notifications\SaveNotificationSettingsData;
use App\Http\Controllers\Api\Controller;
use App\Models\NotificationType;
use App\Support\Api\Attributes\PriceWeight;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationSettingController extends Controller
{
    /**
     * List tenant notification settings for all active notification types.
     *
     * @response array{notification_settings: list<NotificationSettingData>}
     */
    #[ApiResponse(200, 'Tenant notification settings collection', type: 'array{notification_settings: list<array<string, mixed>>}')]
    #[PriceWeight(0)]
    public function index(): JsonResponse
    {
        $this->authorizeApi('notifications.manage', 'notification-settings:read');

        $settings = NotificationType::query()
            ->with('setting')
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->map(fn (NotificationType $type): array => NotificationSettingData::fromType($type)->toArray())
            ->all();

        return $this->respondWithCollection($settings, 'notification_settings');
    }

    /**
     * Bulk update tenant notification settings.
     *
     * @response array{notification_settings: list<NotificationSettingData>}
     */
    #[ApiResponse(200, 'Updated tenant notification settings', type: 'array{notification_settings: list<array<string, mixed>>}')]
    #[PriceWeight(1)]
    public function update(Request $request, SaveNotificationSettings $saveNotificationSettings): JsonResponse
    {
        $this->authorizeApi('notifications.manage', 'notification-settings:write');

        $validated = $request->validate(SaveNotificationSettingsData::rules());
        $dto = new SaveNotificationSettingsData(
            notification_settings: array_map(
                fn (array $row): SaveNotificationSettingItemData => SaveNotificationSettingItemData::from($row),
                $validated['notification_settings'],
            ),
        );

        $saveNotificationSettings($dto);

        $settings = NotificationType::query()
            ->with('setting')
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->map(fn (NotificationType $type): array => NotificationSettingData::fromType($type)->toArray())
            ->all();

        return $this->respondWithCollection($settings, 'notification_settings');
    }
}
