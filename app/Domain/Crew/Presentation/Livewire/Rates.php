<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Livewire;

use App\Domain\Crew\Actions\Rates\CreateCrewRateCard;
use App\Domain\Crew\Actions\Rates\DeactivateCrewRateCard;
use App\Domain\Crew\Actions\Rates\UpdateCrewRateCard;
use App\Domain\Crew\Data\Rates\CreateCrewRateCardData;
use App\Domain\Crew\Data\Rates\UpdateCrewRateCardData;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewRateCard;
use App\Domain\Crew\Models\CrewRole;
use Brick\Math\Exception\MathException;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\WithPagination;

/**
 * @property-read LengthAwarePaginator<int, CrewRateCard> $rates
 * @property-read Collection<int, CrewMember> $members
 * @property-read Collection<int, CrewRole> $roles
 */
#[Layout('components.layouts.app')]
#[Title('Crew rates')]
final class Rates extends CrewComponent
{
    use WithPagination;

    #[Locked]
    public ?int $editingRateId = null;

    public ?int $crew_member_id = null;

    public ?int $crew_role_id = null;

    public string $currency_code = 'GBP';

    public string $effective_from = '';

    public ?string $effective_to = null;

    public ?string $hourly_rate = null;

    public ?string $daily_rate = null;

    public ?string $fixed_rate = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', CrewRateCard::class);
        $this->currency_code = (string) settings('company.base_currency', 'GBP');
        $this->effective_from = today()->toDateString();
    }

    /** @return LengthAwarePaginator<int, CrewRateCard> */
    #[Computed]
    public function rates(): LengthAwarePaginator
    {
        Gate::authorize('viewAny', CrewRateCard::class);
        $query = CrewRateCard::with(['crewMember', 'role'])->orderByDesc('effective_from');
        $ids = auth()->user()->accessibleStoreIds();
        if ($ids !== null) {
            $query->where(function ($query) use ($ids): void {
                $query->whereNull('store_id')->orWhereIn('store_id', $ids);
            });
        }

        return $query->paginate(25);
    }

    /** @return Collection<int, CrewMember> */
    #[Computed]
    public function members(): Collection
    {
        Gate::authorize('create', CrewRateCard::class);

        return CrewMember::where('status', 'active')->orderBy('name')->get(['id', 'name']);
    }

    /** @return Collection<int, CrewRole> */
    #[Computed]
    public function roles(): Collection
    {
        Gate::authorize('create', CrewRateCard::class);

        return CrewRole::where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function save(): void
    {
        Gate::authorize('create', CrewRateCard::class);
        $data = $this->validate(['crew_member_id' => ['nullable', 'integer', 'exists:crew_members,id'], 'crew_role_id' => ['nullable', 'integer', 'exists:crew_roles,id'], 'currency_code' => ['required', 'string', 'size:3'], 'effective_from' => ['required', 'date_format:Y-m-d'], 'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'], 'hourly_rate' => ['nullable', 'numeric', 'min:0'], 'daily_rate' => ['nullable', 'numeric', 'min:0'], 'fixed_rate' => ['nullable', 'numeric', 'min:0']]);
        foreach (['hourly', 'daily', 'fixed'] as $type) {
            $value = $data[$type.'_rate'];
            unset($data[$type.'_rate']);
            if ($value !== null && $value !== '') {
                try {
                    $data[$type.'_rate_minor'] = Money::of($value, $data['currency_code'])->getMinorAmount()->toInt();
                } catch (UnknownCurrencyException|MathException $exception) {
                    $this->addError($type.'_rate', 'Enter a valid amount with the currency’s supported decimal places and a valid ISO currency code.');

                    return;
                }
            }
        }
        $this->performCrewAction(function () use ($data): void {
            if ($this->editingRateId !== null) {
                $card = CrewRateCard::findOrFail($this->editingRateId);
                $data += $card->only(['store_id', 'overtime_multiplier', 'weekend_multiplier', 'night_multiplier', 'holiday_multiplier', 'min_call_hours', 'daily_call_hours', 'travel_rate_minor', 'per_diem_minor', 'notes', 'is_active']);
                $data['engagement_type'] = $card->engagement_type?->value;
                app(UpdateCrewRateCard::class)($card, UpdateCrewRateCardData::from($data));
            } else {
                app(CreateCrewRateCard::class)(CreateCrewRateCardData::from($data));
            }
            $this->reset('editingRateId', 'crew_member_id', 'crew_role_id', 'hourly_rate', 'daily_rate', 'fixed_rate', 'effective_to');
            unset($this->rates);
        });
    }

    public function edit(int $id): void
    {
        $card = CrewRateCard::findOrFail($id);
        Gate::authorize('view', $card);
        Gate::authorize('update', $card);
        $this->editingRateId = $card->id;
        $this->fill($card->only(['crew_member_id', 'crew_role_id', 'currency_code']));
        $this->effective_from = $card->effective_from->toDateString();
        $this->effective_to = $card->effective_to?->toDateString();
        foreach (['hourly', 'daily', 'fixed'] as $type) {
            $this->{$type.'_rate'} = $card->{$type.'_rate_minor'} !== null ? $card->formatMoneyCost($type.'_rate_minor') : null;
        }
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->reset('editingRateId', 'crew_member_id', 'crew_role_id', 'hourly_rate', 'daily_rate', 'fixed_rate', 'effective_to');
        $this->resetValidation();
    }

    public function deactivate(int $id): void
    {
        $this->performCrewAction(function () use ($id): void {
            app(DeactivateCrewRateCard::class)(CrewRateCard::findOrFail($id));
            unset($this->rates);
        });
    }

    public function render(): View
    {
        return view('crew::rates');
    }
}
