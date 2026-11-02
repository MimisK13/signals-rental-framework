<?php

declare(strict_types=1);

namespace App\Domain\Activities\Services;

use App\Domain\Activities\Enums\ActivityType;
use App\Domain\Lists\Models\ListName;
use App\Domain\Lists\Models\ListValue;
use Illuminate\Support\Collection;

/**
 * Centralised, request-scoped resolver for the "Activity Type" list of values.
 *
 * Replaces the repeated `ListName::query()->where('name', 'Activity Type')`
 * lookups that were duplicated across CreateActivity, the activity DTOs, and the
 * calendar/activity form components. Scoped binding resolves the list id and
 * active values once per request without retaining them across Octane lifecycles.
 */
final class ActivityTypeList
{
    /** The name of the list backing activity types. */
    public const LIST_NAME = 'Activity Type';

    private bool $listIdResolved = false;

    private ?int $listId = null;

    /** @var Collection<int, ListValue>|null */
    private ?Collection $activeValues = null;

    /**
     * The id of the "Activity Type" list_name, or null when the list is unseeded.
     */
    public function listId(): ?int
    {
        if ($this->listIdResolved === false) {
            $this->listId = ListName::query()->where('name', self::LIST_NAME)->value('id');
            $this->listIdResolved = true;
        }

        return $this->listId;
    }

    /**
     * The "Activity Type" list's active values, ordered by sort order.
     *
     * @return Collection<int, ListValue>
     */
    public function activeValues(): Collection
    {
        if ($this->activeValues !== null) {
            return $this->activeValues;
        }

        $listId = $this->listId();

        if ($listId === null) {
            return $this->activeValues = collect();
        }

        return $this->activeValues = ListValue::query()
            ->where('list_name_id', $listId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * The default (Task) value id, used when no type is supplied.
     *
     * Anchored on the stable `metadata.icon` key ('task') rather than the
     * user-editable label, so the default survives an admin renaming the "Task"
     * list value. Falls back to the first active value when no Task value exists,
     * and null when the list is unseeded.
     */
    public function defaultId(): ?int
    {
        $values = $this->activeValues();

        $default = $values->first(
            fn (ListValue $value): bool => ($value->metadata['icon'] ?? null) === ActivityType::Task->icon()
        ) ?? $values->first();

        return $default?->id !== null ? (int) $default->id : null;
    }

    /**
     * Alias of {@see defaultId()} — the default activity type is always Task.
     */
    public function taskId(): ?int
    {
        return $this->defaultId();
    }

    /**
     * Forget any resolved state. Call after the "Activity Type" list values change.
     */
    public function clearCache(): void
    {
        $this->listIdResolved = false;
        $this->listId = null;
        $this->activeValues = null;
    }
}
