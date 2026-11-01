<?php

declare(strict_types=1);

namespace App\Domain\Crew\Services;

use App\Domain\Crew\Enums\CrewAssignmentStatus;
use App\Domain\Crew\Enums\CrewExpenseStatus;
use App\Domain\Crew\Enums\CrewRequestStatus;
use App\Domain\Crew\Enums\CrewShiftStatus;
use App\Domain\Crew\Enums\CrewTimesheetStatus;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewExpense;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Models\CrewShift;
use App\Domain\Crew\Models\CrewTimesheet;
use BackedEnum;
use InvalidArgumentException;

/**
 * Lifecycle guard for Crew statuses.
 *
 * Every status-changing Action calls {@see assertCanTransitionTo()} before
 * writing. The transition graph lives on the PHP enums, which remain the single
 * source of truth; this service only maps models to their enum.
 */
final class CrewStatusRegistry
{
    /**
     * @var array<class-string, class-string<BackedEnum>>
     */
    private const STATUS_ENUMS = [
        CrewRequest::class => CrewRequestStatus::class,
        CrewAssignment::class => CrewAssignmentStatus::class,
        CrewShift::class => CrewShiftStatus::class,
        CrewTimesheet::class => CrewTimesheetStatus::class,
        CrewExpense::class => CrewExpenseStatus::class,
    ];

    /**
     * @return list<string>
     */
    public function statuses(string $modelClass): array
    {
        return array_map(
            static fn (BackedEnum $case): string => (string) $case->value,
            $this->enumClass($modelClass)::cases(),
        );
    }

    public function has(string $modelClass, string $status): bool
    {
        return in_array($status, $this->statuses($modelClass), true);
    }

    public function canTransitionTo(string $modelClass, string $from, string $to): bool
    {
        if (! $this->has($modelClass, $from) || ! $this->has($modelClass, $to)) {
            return false;
        }

        $enumClass = $this->enumClass($modelClass);

        // The lifecycle enums expose `canTransitionTo(self $target)`; the call is
        // dynamic because the concrete enum class is resolved from the model.
        return (bool) call_user_func([$enumClass::from($from), 'canTransitionTo'], $enumClass::from($to));
    }

    /**
     * @throws InvalidArgumentException
     */
    public function assertKnown(string $modelClass, string $status): void
    {
        if (! $this->has($modelClass, $status)) {
            throw new InvalidArgumentException(
                sprintf('Unknown %s status [%s].', class_basename($modelClass), $status),
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function assertCanTransitionTo(string $modelClass, string $from, string $to): void
    {
        if ($from === $to) {
            return;
        }

        if (! $this->canTransitionTo($modelClass, $from, $to)) {
            throw new InvalidArgumentException(sprintf(
                '%s status cannot transition from [%s] to [%s].',
                class_basename($modelClass),
                $from,
                $to,
            ));
        }
    }

    /**
     * @return class-string<BackedEnum>
     */
    private function enumClass(string $modelClass): string
    {
        if (! isset(self::STATUS_ENUMS[$modelClass])) {
            throw new InvalidArgumentException(
                sprintf('[%s] does not have a Crew status lifecycle.', $modelClass),
            );
        }

        return self::STATUS_ENUMS[$modelClass];
    }
}
