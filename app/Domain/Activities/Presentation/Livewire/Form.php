<?php

declare(strict_types=1);

namespace App\Domain\Activities\Presentation\Livewire;

use App\Domain\Activities\Actions\CreateActivity;
use App\Domain\Activities\Actions\UpdateActivity;
use App\Domain\Activities\Data\CreateActivityData;
use App\Domain\Activities\Data\UpdateActivityData;
use App\Domain\Activities\Enums\ActivityPriority;
use App\Domain\Activities\Enums\ActivityStatus;
use App\Domain\Activities\Enums\TimeStatus;
use App\Domain\Activities\Models\Activity;
use App\Domain\Activities\Services\ActivityTypeList;
use App\Livewire\Concerns\LoadsCustomFieldValues;
use App\Livewire\Concerns\ReKeysCustomFieldErrors;
use App\Models\CustomField;
use App\Models\CustomFieldGroup;
use App\Models\ListValue;
use App\Models\Member;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\User;
use App\Services\Api\RansackFilter;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * @property-read Collection<int, ListValue> $activityTypes
 * @property-read Collection<int, CustomField> $customFields
 * @property-read Collection<int, mixed> $regardingOptions
 * @property-read Collection<int, mixed> $ownerOptions
 */
#[Layout('components.layouts.app')]
final class Form extends Component
{
    use LoadsCustomFieldValues;
    use ReKeysCustomFieldErrors;

    public ?int $activityId = null;

    public string $subject = '';

    public string $description = '';

    public string $location = '';

    public ?int $typeId = null;

    public int $statusId = 2001;

    public int $priority = 1;

    public int $timeStatus = 0;

    public ?int $ownedBy = null;

    public ?string $regardingType = null;

    public ?int $regardingId = null;

    public ?string $startsAt = null;

    public ?string $endsAt = null;

    public string $regardingSearch = '';

    public string $regardingSelectedName = '';

    public string $ownerSearch = '';

    public string $ownerSelectedName = '';

    /** @var array<string, mixed> */
    public array $customFieldValues = [];

    public function mount(?Activity $activity = null): void
    {
        // Pre-populate from query params
        $regardingType = request()->query('regarding_type');
        if (is_string($regardingType) && in_array($regardingType, ['Member', 'Product', 'StockLevel'])) {
            $this->regardingType = $regardingType;
        }
        $regardingId = request()->query('regarding_id');
        if (is_string($regardingId) && is_numeric($regardingId)) {
            $this->regardingId = (int) $regardingId;
        }

        $this->ownedBy = auth()->id();
        $this->typeId = $this->defaultTypeId();

        if ($activity?->exists) {
            $this->activityId = $activity->id;
            $this->subject = $activity->subject;
            $this->description = $activity->description ?? '';
            $this->location = $activity->location ?? '';
            $this->typeId = $activity->type_id;
            $this->statusId = $activity->status_id->value;
            $this->priority = $activity->priority->value;
            $this->timeStatus = $activity->time_status->value;
            $this->ownedBy = $activity->owned_by;
            $this->regardingType = Activity::shortRegardingType($activity->regarding_type);
            $this->regardingId = $activity->regarding_id;
            $this->startsAt = $activity->starts_at?->format('Y-m-d H:i');
            $this->endsAt = $activity->ends_at?->format('Y-m-d H:i');

            // Load existing custom field values
            $this->customFieldValues = $this->loadCustomFieldValuesFrom($activity);
        }

        // Resolve selected names for autocomplete display
        $this->resolveRegardingName();
        $this->resolveOwnerName();
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
     * Reset regarding selection when the entity type changes.
     */
    public function updatedRegardingType(): void
    {
        $this->regardingId = null;
        $this->regardingSearch = '';
        $this->regardingSelectedName = '';
    }

    /**
     * Search results for the regarding entity autocomplete.
     *
     * @return Collection<int, mixed>
     */
    public function getRegardingOptionsProperty(): Collection
    {
        if ($this->regardingSearch === '' || $this->regardingType === null) {
            return collect();
        }

        return match ($this->regardingType) {
            'Member' => Member::query()
                ->where('is_active', true)
                ->where('name', 'ilike', '%'.RansackFilter::escapeLike($this->regardingSearch).'%')
                ->orderBy('name')
                ->limit(15)
                ->get(['id', 'name']),
            'Product' => Product::query()
                ->where('is_active', true)
                ->where('name', 'ilike', '%'.RansackFilter::escapeLike($this->regardingSearch).'%')
                ->orderBy('name')
                ->limit(15)
                ->get(['id', 'name']),
            'StockLevel' => StockLevel::query()
                ->where('item_name', 'ilike', '%'.RansackFilter::escapeLike($this->regardingSearch).'%')
                ->orderBy('item_name')
                ->limit(15)
                ->get(['id', 'item_name as name']),
            default => collect(),
        };
    }

    /**
     * Select a regarding entity from the autocomplete results.
     */
    public function selectRegarding(int $id, string $name): void
    {
        $this->regardingId = $id;
        $this->regardingSelectedName = $name;
        $this->regardingSearch = '';
    }

    /**
     * Clear the selected regarding entity.
     */
    public function clearRegarding(): void
    {
        $this->regardingId = null;
        $this->regardingSelectedName = '';
        $this->regardingSearch = '';
    }

    /**
     * Search results for the owner autocomplete.
     *
     * @return Collection<int, mixed>
     */
    public function getOwnerOptionsProperty(): Collection
    {
        if ($this->ownerSearch === '') {
            return collect();
        }

        return User::query()
            ->where('is_active', true)
            ->where('name', 'ilike', '%'.RansackFilter::escapeLike($this->ownerSearch).'%')
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name']);
    }

    /**
     * Select an owner from the autocomplete results.
     */
    public function selectOwner(int $id, string $name): void
    {
        $this->ownedBy = $id;
        $this->ownerSelectedName = $name;
        $this->ownerSearch = '';
    }

    /**
     * Clear the selected owner.
     */
    public function clearOwner(): void
    {
        $this->ownedBy = null;
        $this->ownerSelectedName = '';
        $this->ownerSearch = '';
    }

    public function save(): void
    {
        $this->validate([
            'subject' => ['required', 'string', 'max:255'],
        ]);

        $payload = [
            'subject' => $this->subject,
            'description' => $this->description ?: null,
            'location' => $this->location ?: null,
            'type_id' => $this->typeId,
            'status_id' => $this->statusId,
            'priority' => $this->priority,
            'time_status' => $this->timeStatus,
            'owned_by' => $this->ownedBy,
            'regarding_type' => $this->regardingType,
            'regarding_id' => $this->regardingId,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'custom_fields' => $this->customFieldValues,
        ];

        try {
            if ($this->activityId) {
                $activity = Activity::findOrFail($this->activityId);
                $result = (new UpdateActivity)($activity, UpdateActivityData::from($payload));
            } else {
                $result = (new CreateActivity)(CreateActivityData::from($payload));
            }

            $this->redirect(route('activities.show', $result->id), navigate: true);
        } catch (ValidationException $e) {
            $this->reKeyCustomFieldErrors($e, $this->customFields->pluck('name')->all());
        }
    }

    /**
     * Active custom field definitions for the Activity module.
     *
     * @return Collection<int, CustomField>
     */
    #[Computed]
    public function customFields(): Collection
    {
        return CustomField::query()
            ->forModule('Activity')
            ->active()
            ->with(['group', 'listName.values'])
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'isEditing' => $this->activityId !== null,
            'activityTypes' => $this->activityTypes,
            'activityStatuses' => ActivityStatus::cases(),
            'activityPriorities' => ActivityPriority::cases(),
            'timeStatuses' => TimeStatus::cases(),
            'groupedCustomFields' => $this->customFields->groupBy(function (CustomField $field): string {
                $group = $field->getRelation('group');

                return $group instanceof CustomFieldGroup ? $group->name : 'General';
            }),
        ];
    }

    /**
     * Resolve the display name for the currently selected regarding entity.
     */
    private function resolveRegardingName(): void
    {
        if ($this->regardingId === null || $this->regardingType === null) {
            return;
        }

        $this->regardingSelectedName = match ($this->regardingType) {
            'Member' => Member::query()->where('id', $this->regardingId)->value('name') ?? '',
            'Product' => Product::query()->where('id', $this->regardingId)->value('name') ?? '',
            'StockLevel' => StockLevel::query()->where('id', $this->regardingId)->value('item_name') ?? '',
            default => '',
        };
    }

    /**
     * Resolve the display name for the currently selected owner.
     */
    private function resolveOwnerName(): void
    {
        if ($this->ownedBy === null) {
            return;
        }

        $this->ownerSelectedName = User::query()->where('id', $this->ownedBy)->value('name') ?? '';
    }

    public function render(): View
    {
        return view('activities::form', $this->with());
    }
}
