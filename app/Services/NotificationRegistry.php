<?php

namespace App\Services;

use App\Enums\NotificationAudience;
use App\Models\NotificationType;

class NotificationRegistry
{
    /**
     * @var array<string, array{
     *     category: string,
     *     name: string,
     *     description: string,
     *     audience?: NotificationAudience|string,
     *     available_channels: list<string>,
     *     default_channels: list<string>,
     *     default_recipients?: list<array<string, mixed>>|null,
     *     data_schema?: array<string, mixed>|null,
     *     is_system?: bool
     * }>
     */
    private array $types = [];

    /**
     * Register a notification type.
     *
     * @param  array{
     *     category: string,
     *     name: string,
     *     description: string,
     *     audience?: NotificationAudience|string,
     *     available_channels: list<string>,
     *     default_channels: list<string>,
     *     default_recipients?: list<array<string, mixed>>|null,
     *     data_schema?: array<string, mixed>|null,
     *     is_system?: bool
     * }  $definition
     */
    public function register(string $key, array $definition): void
    {
        $this->types[$key] = $definition;
    }

    /**
     * Register multiple notification types at once.
     *
     * @param  array<string, array{
     *     category: string,
     *     name: string,
     *     description: string,
     *     audience?: NotificationAudience|string,
     *     available_channels: list<string>,
     *     default_channels: list<string>,
     *     default_recipients?: list<array<string, mixed>>|null,
     *     data_schema?: array<string, mixed>|null,
     *     is_system?: bool
     * }>  $types
     */
    public function registerMany(array $types): void
    {
        foreach ($types as $key => $definition) {
            $this->register($key, $definition);
        }
    }

    /**
     * Get a notification type definition by key.
     *
     * @return array{
     *     category: string,
     *     name: string,
     *     description: string,
     *     audience?: NotificationAudience|string,
     *     available_channels: list<string>,
     *     default_channels: list<string>,
     *     default_recipients?: list<array<string, mixed>>|null,
     *     data_schema?: array<string, mixed>|null,
     *     is_system?: bool
     * }|null
     */
    public function get(string $key): ?array
    {
        return $this->types[$key] ?? null;
    }

    /**
     * Get all registered notification type definitions.
     *
     * @return array<string, array{
     *     category: string,
     *     name: string,
     *     description: string,
     *     audience?: NotificationAudience|string,
     *     available_channels: list<string>,
     *     default_channels: list<string>,
     *     default_recipients?: list<array<string, mixed>>|null,
     *     data_schema?: array<string, mixed>|null,
     *     is_system?: bool
     * }>
     */
    public function all(): array
    {
        return $this->types;
    }

    /**
     * Get notification types grouped by category.
     *
     * @return array<string, array<string, array{
     *     category: string,
     *     name: string,
     *     description: string,
     *     audience?: NotificationAudience|string,
     *     available_channels: list<string>,
     *     default_channels: list<string>,
     *     default_recipients?: list<array<string, mixed>>|null,
     *     data_schema?: array<string, mixed>|null,
     *     is_system?: bool
     * }>>
     */
    public function grouped(): array
    {
        $groups = [];

        foreach ($this->types as $key => $definition) {
            $groups[$definition['category']][$key] = $definition;
        }

        ksort($groups);

        return $groups;
    }

    /**
     * Sync registered type metadata to the database without touching tenant settings.
     */
    public function syncToDatabase(): void
    {
        foreach ($this->types as $key => $definition) {
            $audience = $definition['audience'] ?? NotificationAudience::Internal;
            if ($audience instanceof NotificationAudience) {
                $audience = $audience->value;
            }

            NotificationType::query()->updateOrCreate(
                ['key' => $key],
                [
                    'category' => $definition['category'],
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'audience' => $audience,
                    'available_channels' => $definition['available_channels'],
                    'default_channels' => $definition['default_channels'],
                    'default_recipients' => $definition['default_recipients'] ?? null,
                    'data_schema' => $definition['data_schema'] ?? null,
                    'is_system' => $definition['is_system'] ?? false,
                    'source' => 'core',
                ],
            );
        }
    }

    /**
     * Check if a notification type is registered.
     */
    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }
}
