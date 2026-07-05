<?php

namespace App\Services\Notifications;

/**
 * Registry of merge-field filters for communication templates.
 *
 * Core filters register on boot; plugins extend via {@see PluginRegistrar::registerFilter()}.
 *
 * @phpstan-type MergeFieldFilter callable(mixed $value, ?string $arg, MergeFieldRenderContext $context): mixed
 */
class MergeFieldFilterRegistry
{
    /** @var array<string, MergeFieldFilter> */
    private array $filters = [];

    /**
     * @param  MergeFieldFilter  $filter
     */
    public function register(string $name, callable $filter): void
    {
        $this->filters[$name] = $filter;
    }

    public function has(string $name): bool
    {
        return isset($this->filters[$name]);
    }

    /**
     * @return MergeFieldFilter|null
     */
    public function get(string $name): ?callable
    {
        return $this->filters[$name] ?? null;
    }

    /**
     * @return array<string, MergeFieldFilter>
     */
    public function all(): array
    {
        return $this->filters;
    }

    public function apply(string $name, mixed $value, ?string $arg, MergeFieldRenderContext $context): mixed
    {
        $filter = $this->get($name);

        if ($filter === null) {
            return $value;
        }

        return $filter($value, $arg, $context);
    }
}
