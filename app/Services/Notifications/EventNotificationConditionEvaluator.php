<?php

namespace App\Services\Notifications;

use App\Events\AuditableEvent;
use Illuminate\Database\Eloquent\Model;

/**
 * Evaluates config-driven `when` clauses against an {@see AuditableEvent}.
 */
class EventNotificationConditionEvaluator
{
    /**
     * @param  list<array{source: string, field: string, operator: string, value?: mixed}>|null  $conditions
     */
    public function matches(?array $conditions, AuditableEvent $event): bool
    {
        if ($conditions === null || $conditions === []) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (! $this->matchesCondition($condition, $event)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{source: string, field: string, operator: string, value?: mixed}  $condition
     */
    private function matchesCondition(array $condition, AuditableEvent $event): bool
    {
        $actual = $this->resolveValue(
            source: (string) $condition['source'],
            field: (string) $condition['field'],
            event: $event,
        );

        $operator = (string) $condition['operator'];
        $expected = $condition['value'] ?? null;

        return match ($operator) {
            'eq' => $this->normalizeComparable($actual) == $this->normalizeComparable($expected),
            'neq' => $this->normalizeComparable($actual) != $this->normalizeComparable($expected),
            'in' => is_array($expected) && in_array($this->normalizeComparable($actual), array_map($this->normalizeComparable(...), $expected), false),
            'not_in' => is_array($expected) && ! in_array($this->normalizeComparable($actual), array_map($this->normalizeComparable(...), $expected), false),
            'present' => $actual !== null && $actual !== '',
            'blank' => $actual === null || $actual === '',
            default => false,
        };
    }

    private function normalizeComparable(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        return $value;
    }

    private function resolveValue(string $source, string $field, AuditableEvent $event): mixed
    {
        $bag = match ($source) {
            'new' => $event->newValues ?? [],
            'old' => $event->oldValues ?? [],
            'metadata' => $event->metadata ?? [],
            'model' => $this->modelAttributes($event->model),
            default => [],
        };

        return data_get($bag, $field);
    }

    /**
     * @return array<string, mixed>
     */
    private function modelAttributes(Model $model): array
    {
        $attributes = $model->getAttributes();

        foreach ($model->getCasts() as $key => $cast) {
            if (! array_key_exists($key, $attributes)) {
                continue;
            }

            $attributes[$key] = $model->getAttribute($key);
        }

        return $attributes;
    }
}
