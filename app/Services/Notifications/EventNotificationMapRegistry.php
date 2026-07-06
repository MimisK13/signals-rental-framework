<?php

namespace App\Services\Notifications;

/**
 * Config-driven map of audit actions to notification type dispatch entries.
 *
 * Core mappings load from `config/notifications.php`; plugins register
 * additional entries through the same registry seam.
 */
class EventNotificationMapRegistry
{
    /**
     * @var array<string, list<array{type?: string, types?: list<string>, when?: list<array<string, mixed>>}>>
     */
    private array $entries = [];

    public function __construct()
    {
        /** @var array<string, list<array{type?: string, types?: list<string>, when?: list<array<string, mixed>>}>> $core */
        $core = config('notifications.event_map', []);

        foreach ($core as $action => $entries) {
            $this->entries[$action] = $entries;
        }
    }

    /**
     * Register or extend mappings for an audit action.
     *
     * @param  list<array{type?: string, types?: list<string>, when?: list<array<string, mixed>>}>  $entries
     */
    public function register(string $action, array $entries): void
    {
        $this->entries[$action] = array_merge($this->entries[$action] ?? [], $entries);
    }

    /**
     * @return list<array{type?: string, types?: list<string>, when?: list<array<string, mixed>>}>
     */
    public function resolve(string $action): array
    {
        return $this->entries[$action] ?? [];
    }

    /**
     * @param  array{type?: string, types?: list<string>, when?: list<array<string, mixed>>}  $entry
     * @return list<string>
     */
    public function notificationTypesForEntry(array $entry): array
    {
        if (array_key_exists('types', $entry)) {
            return $entry['types'];
        }

        if (array_key_exists('type', $entry)) {
            return [$entry['type']];
        }

        return [];
    }

    public function has(string $action): bool
    {
        return ($this->entries[$action] ?? []) !== [];
    }
}
