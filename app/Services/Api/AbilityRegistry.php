<?php

namespace App\Services\Api;

class AbilityRegistry
{
    /**
     * @var array<string, array{key: string, label: string, group: string, description: string}>
     */
    private array $abilities = [];

    /**
     * @param  array{label: string, description?: string, group?: string}  $definition
     */
    public function register(string $key, array $definition): void
    {
        $group = $definition['group'] ?? str($key)->before(':')->toString();

        $this->abilities[$key] = [
            'key' => $key,
            'label' => $definition['label'],
            'group' => $group,
            'description' => $definition['description'] ?? $definition['label'],
        ];
    }

    /**
     * @param  array<string, array{label: string, description?: string, group?: string}>  $abilities
     */
    public function registerMany(array $abilities): void
    {
        foreach ($abilities as $key => $definition) {
            $this->register($key, $definition);
        }
    }

    /**
     * @return array{key: string, label: string, group: string, description: string}|null
     */
    public function get(string $key): ?array
    {
        return $this->abilities[$key] ?? null;
    }

    /**
     * @return array<string, array{key: string, label: string, group: string, description: string}>
     */
    public function all(): array
    {
        return $this->abilities;
    }

    /**
     * @return array<string, array{label: string, abilities: array<string, string>}>
     */
    public function groups(): array
    {
        $groups = [];

        foreach ($this->abilities as $ability) {
            $groups[$ability['group']]['label'] = (string) str($ability['group'])->headline();
            $groups[$ability['group']]['abilities'][$ability['key']] = $ability['label'];
        }

        ksort($groups);

        return $groups;
    }

    /**
     * @return array<string, string>
     */
    public function labels(): array
    {
        return array_map(
            static fn (array $ability): string => $ability['label'],
            $this->abilities,
        );
    }

    public function has(string $key): bool
    {
        return isset($this->abilities[$key]);
    }
}
