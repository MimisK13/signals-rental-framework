<?php

namespace App\Concerns;

use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Re-reads a model row under SELECT FOR UPDATE inside an open transaction and
 * re-asserts preconditions before firing Verbs events.
 */
trait LocksModelsForMutation
{
    /**
     * Lock a model row and optionally assert its status column matches an allow-list.
     *
     * Call only inside {@see CommitsVerbsEvents::commitVerbs()} (or another DB transaction).
     *
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @param  list<string|BackedEnum|UnitEnum>|string|BackedEnum|UnitEnum  $allowedStatuses
     * @return TModel
     */
    protected function lockModelForMutation(
        Model $model,
        array|string|BackedEnum|UnitEnum|null $allowedStatuses = null,
        string $statusColumn = 'status',
        ?string $failureKey = null,
        ?string $failureMessage = null,
        ?Closure $assert = null,
    ): Model {
        $locked = $model->newQuery()
            ->whereKey($model->getKey())
            ->lockForUpdate()
            ->first();

        if ($locked === null) {
            throw ValidationException::withMessages([
                $failureKey ?? $model->getTable() => [
                    sprintf('%s no longer exists.', class_basename($model)),
                ],
            ]);
        }

        if ($allowedStatuses !== null) {
            $allowed = is_array($allowedStatuses) ? $allowedStatuses : [$allowedStatuses];
            $normalizedAllowed = array_map(
                fn (string|BackedEnum|UnitEnum $status): string => $this->normalizeStatusValue($status),
                $allowed,
            );

            $current = $this->normalizeStatusValue($locked->{$statusColumn});

            if (! in_array($current, $normalizedAllowed, true)) {
                throw ValidationException::withMessages([
                    $failureKey ?? $statusColumn => [
                        $failureMessage ?? sprintf(
                            'Cannot proceed: %s status is [%s].',
                            class_basename($model),
                            $current,
                        ),
                    ],
                ]);
            }
        }

        if ($assert !== null) {
            $assert($locked);
        }

        return $locked;
    }

    private function normalizeStatusValue(mixed $status): string
    {
        if ($status instanceof BackedEnum) {
            return (string) $status->value;
        }

        if ($status instanceof UnitEnum) {
            return $status->name;
        }

        return (string) $status;
    }
}
