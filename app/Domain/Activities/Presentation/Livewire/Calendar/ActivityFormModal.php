<?php

declare(strict_types=1);

namespace App\Domain\Activities\Presentation\Livewire\Calendar;

use App\Domain\Activities\Actions\CreateActivity;
use App\Domain\Activities\Actions\UpdateActivity;
use App\Domain\Activities\Data\CreateActivityData;
use App\Domain\Activities\Data\UpdateActivityData;
use App\Domain\Activities\Enums\ActivityPriority;
use App\Domain\Activities\Enums\ActivityStatus;
use App\Domain\Activities\Enums\TimeStatus;
use App\Domain\Activities\Models\Activity;
use App\Domain\Activities\Services\ActivityTypeList;
use App\Domain\Activities\Services\Calendar\OwnerColorResolver;
use App\Domain\Lists\Models\ListValue;
use App\Models\User;
use App\Support\Timezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property-read Collection<int, User> $staff
 * @property-read Collection<int, ListValue> $activityTypes
 */
final class ActivityFormModal extends Component
{
    public ?int $activityId = null;

    public string $subject = '';

    public ?string $description = null;

    public ?string $location = null;

    public ?int $typeId = null;

    public int $statusId = 2001;

    public int $priority = 1;

    public int $timeStatus = 0;

    public ?int $ownedBy = null;

    public ?string $regardingType = null;

    public ?int $regardingId = null;

    public ?string $startsAt = null;

    public ?string $endsAt = null;

    /** @var list<int> */
    public array $participantIds = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('activities.access') ?? false, 403);
    }

    /**
     * Open the modal in create or edit mode. Named parameters match the calendar
     * event contract (dispatched as object keys / PHP named args):
     *   - activityId            → edit that activity
     *   - owned_by, starts_at   → prefill a new activity from a day/week slot click
     *   - date                  → prefill a new activity from a month-day click
     *                             (defaults to the working-hours window)
     */
    #[On('calendar-open-form')]
    public function open(?int $activityId = null, ?int $owned_by = null, ?string $starts_at = null, ?string $date = null): void
    {
        $this->resetForm();

        if ($activityId !== null) {
            $this->loadActivity($activityId);

            return;
        }

        // Create mode — prefill owner and start/end from the slot or month-day payload.
        $this->ownedBy = $owned_by ?? auth()->id();

        if ($starts_at !== null) {
            $this->startsAt = $starts_at;
            $this->endsAt = Carbon::parse($starts_at)->addHour()->format('Y-m-d H:i');
        } elseif ($date !== null) {
            $start = substr((string) settings('scheduling.default_start_time'), 0, 5);
            $end = substr((string) settings('scheduling.default_end_time'), 0, 5);
            $this->startsAt = $date.' '.$start;
            $this->endsAt = $date.' '.$end;
        }
    }

    public function save(): void
    {
        $this->validate([
            'subject' => ['required', 'string', 'max:255'],
        ]);

        abort_unless(
            auth()->user()?->can($this->activityId !== null ? 'activities.edit' : 'activities.create') ?? false,
            403,
        );

        // The owner is never also a participant.
        $ownerMemberId = $this->ownerMemberId();
        $participantIds = array_values(array_filter(
            $this->participantIds,
            fn (int $id): bool => $id !== $ownerMemberId,
        ));

        $payload = [
            'subject' => $this->subject,
            'description' => $this->description ?: null,
            'location' => $this->location ?: null,
            'type_id' => $this->typeId,
            'status_id' => $this->statusId,
            'completed' => $this->statusId === ActivityStatus::Completed->value,
            'priority' => $this->priority,
            'time_status' => $this->timeStatus,
            'owned_by' => $this->ownedBy,
            'regarding_type' => $this->regardingType,
            'regarding_id' => $this->regardingId,
            'starts_at' => $this->toUtc($this->startsAt),
            'ends_at' => $this->toUtc($this->endsAt),
            'participants' => array_map(fn (int $id): array => ['member_id' => $id], $participantIds),
        ];

        if ($this->activityId !== null) {
            $activity = Activity::findOrFail($this->activityId);
            app(UpdateActivity::class)($activity, UpdateActivityData::from($payload));
        } else {
            app(CreateActivity::class)(CreateActivityData::from($payload));
        }

        $this->dispatch('calendar-refresh');
        $this->dispatch('activity-saved', message: $this->activityId !== null ? 'Activity updated' : 'Activity created');
    }

    public function toggleParticipant(int $memberId): void
    {
        if (in_array($memberId, $this->participantIds, true)) {
            $this->participantIds = array_values(array_filter($this->participantIds, fn (int $id): bool => $id !== $memberId));
        } else {
            $this->participantIds = [...$this->participantIds, $memberId];
        }
    }

    /**
     * The owner can never also be a participant — drop them if the owner changes.
     */
    public function updatedOwnedBy(): void
    {
        $ownerMemberId = $this->ownerMemberId();

        if ($ownerMemberId !== null) {
            $this->participantIds = array_values(array_filter($this->participantIds, fn (int $id): bool => $id !== $ownerMemberId));
        }
    }

    /**
     * The linked member id of the currently-selected owner (or null).
     */
    private function ownerMemberId(): ?int
    {
        $memberId = $this->staff->firstWhere('id', $this->ownedBy)?->member_id;

        return $memberId !== null ? (int) $memberId : null;
    }

    /**
     * Convert a local 'Y-m-d H:i' form value (company timezone) to a UTC string for storage.
     */
    private function toUtc(?string $local): ?string
    {
        if ($local === null || $local === '') {
            return null;
        }

        return app(Timezone::class)->parseUserInput($local)->format('Y-m-d H:i');
    }

    /**
     * Reset every form property to its create-mode default.
     */
    private function resetForm(): void
    {
        $this->reset([
            'activityId',
            'subject',
            'description',
            'location',
            'regardingType',
            'regardingId',
            'startsAt',
            'endsAt',
            'participantIds',
        ]);

        $this->typeId = $this->defaultTypeId();
        $this->statusId = ActivityStatus::Scheduled->value;
        $this->priority = ActivityPriority::Normal->value;
        $this->timeStatus = TimeStatus::Free->value;
        $this->ownedBy = auth()->id();

        $this->resetValidation();
    }

    /**
     * Hydrate the form from an existing activity for edit mode.
     */
    private function loadActivity(int $activityId): void
    {
        $activity = Activity::with('participants')->findOrFail($activityId);

        $this->activityId = $activity->id;
        $this->subject = $activity->subject;
        $this->description = $activity->description;
        $this->location = $activity->location;
        $this->typeId = $activity->type_id;
        $this->statusId = $activity->status_id->value;
        $this->priority = $activity->priority->value;
        $this->timeStatus = $activity->time_status->value;
        $this->ownedBy = $activity->owned_by;
        $this->regardingType = Activity::shortRegardingType($activity->regarding_type);
        $this->regardingId = $activity->regarding_id;
        $timezone = app(Timezone::class);
        $this->startsAt = $activity->starts_at !== null ? $timezone->toLocal($activity->starts_at)->format('Y-m-d H:i') : null;
        $this->endsAt = $activity->ends_at !== null ? $timezone->toLocal($activity->ends_at)->format('Y-m-d H:i') : null;
        $this->participantIds = $activity->participants->pluck('member_id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * The "Activity Type" list's active values, ordered for the type dropdown.
     *
     * @return Collection<int, ListValue>
     */
    #[Computed]
    public function activityTypes(): Collection
    {
        return app(ActivityTypeList::class)->activeValues();
    }

    /**
     * The default type (Task) value id for new activities.
     */
    private function defaultTypeId(): ?int
    {
        return app(ActivityTypeList::class)->defaultId();
    }

    /**
     * Active staff (system users) for the owner + participant pickers.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function staff(): Collection
    {
        $staff = User::query()
            ->with('member')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Logged-in user first, then the rest by name.
        $authId = auth()->id();

        return $staff->filter(fn (User $u): bool => $u->id === $authId)
            ->merge($staff->filter(fn (User $u): bool => $u->id !== $authId))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'staff' => $this->staff,
            'colorResolver' => app(OwnerColorResolver::class),
            'activityTypes' => $this->activityTypes,
            'activityStatuses' => ActivityStatus::cases(),
            'activityPriorities' => ActivityPriority::cases(),
            'timeStatuses' => TimeStatus::cases(),
        ];
    }

    public function render(): View
    {
        return view('activities::calendar.activity-form-modal', $this->with());
    }
}
