<?php

namespace App\Data\Notifications;

use Spatie\LaravelData\Data;

class SaveNotificationSettingsData extends Data
{
    /**
     * @param  list<SaveNotificationSettingItemData>  $notification_settings
     */
    public function __construct(
        public array $notification_settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'notification_settings' => ['required', 'array'],
            'notification_settings.*.notification_type_id' => ['required', 'integer', 'exists:notification_types,id'],
            'notification_settings.*.is_enabled' => ['sometimes', 'boolean'],
            'notification_settings.*.channels' => ['sometimes', 'array'],
            'notification_settings.*.channels.*' => ['string'],
            'notification_settings.*.recipient_rules' => ['sometimes', 'array'],
            'notification_settings.*.audiences' => ['sometimes', 'array'],
            'notification_settings.*.audiences.internal.channels' => ['sometimes', 'array'],
            'notification_settings.*.audiences.internal.channels.*' => ['string'],
            'notification_settings.*.audiences.internal.recipient_rules' => ['sometimes', 'array'],
            'notification_settings.*.audiences.external.channels' => ['sometimes', 'array'],
            'notification_settings.*.audiences.external.channels.*' => ['string'],
            'notification_settings.*.audiences.external.recipient_rules' => ['sometimes', 'array'],
        ];
    }
}
