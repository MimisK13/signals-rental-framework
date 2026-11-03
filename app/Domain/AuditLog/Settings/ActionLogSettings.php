<?php

declare(strict_types=1);

namespace App\Domain\AuditLog\Settings;

use App\Domain\Settings\Settings\SettingsDefinition;

class ActionLogSettings extends SettingsDefinition
{
    public function group(): string
    {
        return 'action-log';
    }

    public function defaults(): array
    {
        return [
            'retention_months' => 12,
        ];
    }

    public function rules(): array
    {
        return [
            'retention_months' => ['required', 'integer', 'min:1', 'max:120'],
        ];
    }

    public function types(): array
    {
        return [
            'retention_months' => 'integer',
        ];
    }
}
