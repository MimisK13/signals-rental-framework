<?php

namespace App\Settings;

class NotificationSettings extends SettingsDefinition
{
    public function group(): string
    {
        return 'notifications';
    }

    public function defaults(): array
    {
        return [
            'communication_log_retention_days' => 365,
            'communication_log_prune_safety_days' => 7,
            'unsubscribe_url_ttl_days' => 365,
        ];
    }

    public function rules(): array
    {
        return [
            'communication_log_retention_days' => ['required', 'integer', 'min:30', 'max:3650'],
            'communication_log_prune_safety_days' => ['required', 'integer', 'min:1', 'max:90'],
            'unsubscribe_url_ttl_days' => ['required', 'integer', 'min:1', 'max:3650'],
        ];
    }

    public function types(): array
    {
        return [
            'communication_log_retention_days' => 'integer',
            'communication_log_prune_safety_days' => 'integer',
            'unsubscribe_url_ttl_days' => 'integer',
        ];
    }
}
