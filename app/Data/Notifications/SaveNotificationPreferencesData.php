<?php

namespace App\Data\Notifications;

use Spatie\LaravelData\Data;

class SaveNotificationPreferencesData extends Data
{
    /**
     * @param  list<SaveNotificationPreferenceItemData>  $preferences
     */
    public function __construct(
        public array $preferences,
        public ?int $target_user_id = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'preferences' => ['required', 'array'],
            'preferences.*.notification_type_id' => ['required', 'integer', 'exists:notification_types,id'],
            'preferences.*.channels' => ['nullable', 'array'],
            'preferences.*.channels.*' => ['string'],
            'preferences.*.is_muted' => ['boolean'],
            'target_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
