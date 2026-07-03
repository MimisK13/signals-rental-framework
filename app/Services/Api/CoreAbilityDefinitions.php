<?php

namespace App\Services\Api;

/**
 * Core Sanctum token abilities registered by the open-source framework.
 */
final class CoreAbilityDefinitions
{
    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function all(): array
    {
        return [
            'members:read' => ['label' => 'Read members', 'description' => 'Read members'],
            'members:write' => ['label' => 'Write members', 'description' => 'Write members'],
            'countries:read' => ['label' => 'Read countries', 'description' => 'Read countries'],
            'currencies:read' => ['label' => 'Read currencies', 'description' => 'Read currencies'],
            'exchange_rates:read' => ['label' => 'Read exchange rates', 'description' => 'Read exchange rates'],
            'exchange_rates:write' => ['label' => 'Write exchange rates', 'description' => 'Write exchange rates'],
            'products:read' => ['label' => 'Read products & product groups', 'description' => 'Read products & product groups'],
            'products:write' => ['label' => 'Write products & product groups', 'description' => 'Write products & product groups'],
            'stock:read' => ['label' => 'Read stock levels & transactions', 'description' => 'Read stock levels & transactions'],
            'stock:write' => ['label' => 'Write stock levels & transactions', 'description' => 'Write stock levels & transactions'],
            'availability:read' => ['label' => 'Read product availability', 'description' => 'Read product availability'],
            'containers:read' => ['label' => 'Read containers & contents', 'description' => 'Read containers & contents'],
            'containers:write' => ['label' => 'Pack & unpack containers', 'description' => 'Pack & unpack containers'],
            'kits:read' => ['label' => 'Read kit compositions', 'description' => 'Read kit compositions'],
            'kits:write' => ['label' => 'Manage kit compositions', 'description' => 'Manage kit compositions'],
            'activities:read' => ['label' => 'Read activities', 'description' => 'Read activities'],
            'activities:write' => ['label' => 'Write activities', 'description' => 'Write activities'],
            'opportunities:read' => ['label' => 'Read opportunities', 'description' => 'Read opportunities'],
            'opportunities:write' => ['label' => 'Write opportunities', 'description' => 'Write opportunities'],
            'shortages:read' => ['label' => 'Read shortages & resolutions', 'description' => 'Read shortages & resolutions'],
            'shortages:write' => ['label' => 'Write shortage resolutions', 'description' => 'Write shortage resolutions'],
            'rates:read' => ['label' => 'Read rate definitions & product rates', 'description' => 'Read rate definitions & product rates'],
            'rates:write' => ['label' => 'Write rate definitions & product rates', 'description' => 'Write rate definitions & product rates'],
            'pricing:read' => ['label' => 'Read pricing categories', 'description' => 'Read discount and price categories'],
            'pricing:write' => ['label' => 'Write pricing categories', 'description' => 'Write discount and price categories'],
            'schema:read' => ['label' => 'Read schema / field metadata', 'description' => 'Read schema / field metadata'],
            'custom-fields:read' => ['label' => 'Read custom fields', 'description' => 'Read custom fields'],
            'custom-fields:write' => ['label' => 'Write custom fields', 'description' => 'Write custom fields'],
            'static-data:read' => ['label' => 'Read static data (lists)', 'description' => 'Read static data (lists)'],
            'static-data:write' => ['label' => 'Write static data (lists)', 'description' => 'Write static data (lists)'],
            'tax-classes:read' => ['label' => 'Read tax classes', 'description' => 'Read tax classes'],
            'tax-classes:write' => ['label' => 'Write tax classes', 'description' => 'Write tax classes'],
            'settings:read' => ['label' => 'Read settings', 'description' => 'Read settings'],
            'settings:write' => ['label' => 'Write settings', 'description' => 'Write settings'],
            'users:read' => ['label' => 'Read users', 'description' => 'Read users'],
            'users:write' => ['label' => 'Write users', 'description' => 'Write users'],
            'roles:read' => ['label' => 'Read roles', 'description' => 'Read roles'],
            'roles:write' => ['label' => 'Write roles', 'description' => 'Write roles'],
            'webhooks:manage' => ['label' => 'Manage webhooks', 'description' => 'Manage webhooks'],
            'system:read' => ['label' => 'Read system info', 'description' => 'Read system info'],
            'action-log:read' => ['label' => 'Read action logs', 'description' => 'Read action logs'],
            'api-usage:read' => ['label' => 'Read API usage', 'description' => 'Read API usage aggregates', 'group' => 'api'],
            'api-log:read' => ['label' => 'Read API request logs', 'description' => 'Read API request logs', 'group' => 'api'],
        ];
    }
}
