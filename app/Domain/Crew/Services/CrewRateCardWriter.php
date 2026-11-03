<?php

declare(strict_types=1);

namespace App\Domain\Crew\Services;

use App\Domain\Crew\Enums\CrewEngagementType;
use App\Domain\Crew\Models\CrewRateCard;
use App\Domain\Stores\Models\Store;
use Brick\Money\Currency;
use Brick\Money\Exception\UnknownCurrencyException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CrewRateCardWriter
{
    /** @param array<string,mixed> $attributes */
    public function save(array $attributes, ?CrewRateCard $card = null): CrewRateCard
    {
        $rules = [
            'currency_code' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'crew_member_id' => ['nullable', 'integer', 'exists:crew_members,id'],
            'crew_role_id' => ['nullable', 'integer', 'exists:crew_roles,id'],
            'engagement_type' => ['nullable', Rule::enum(CrewEngagementType::class)],
            'notes' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['boolean'],
        ];
        foreach (['hourly_rate_minor', 'daily_rate_minor', 'fixed_rate_minor', 'travel_rate_minor', 'per_diem_minor'] as $field) {
            $rules[$field] = ['nullable', 'integer', 'min:0', 'max:1000000000'];
        }
        foreach (['overtime_multiplier', 'weekend_multiplier', 'night_multiplier', 'holiday_multiplier'] as $field) {
            $rules[$field] = ['nullable', 'numeric', 'min:1', 'max:5'];
        }
        foreach (['min_call_hours', 'daily_call_hours'] as $field) {
            $rules[$field] = ['nullable', 'numeric', 'min:0', 'max:24'];
        }
        $rules['daily_call_hours'] = ['nullable', 'numeric', 'min:1', 'max:24'];
        $attributes = Validator::make($attributes, $rules)->validate();
        try {
            Currency::of($attributes['currency_code']);
        } catch (UnknownCurrencyException) {
            throw ValidationException::withMessages(['currency_code' => 'Choose a supported ISO currency code.']);
        }

        if (collect(['hourly_rate_minor', 'daily_rate_minor', 'fixed_rate_minor'])->every(static function (string $field) use ($attributes): bool {
            return $attributes[$field] === null;
        })) {
            throw ValidationException::withMessages(['hourly_rate_minor' => 'At least one rate is required.']);
        }
        if ($attributes['store_id'] !== null) {
            abort_unless(auth()->user()?->canAccessStore($attributes['store_id']), 403);
        }
        // Serialize overlapping-card checks, including inserts into an empty card set.
        Store::query()->orderBy('id')->lockForUpdate()->get(['id']);
        $overlap = CrewRateCard::query()->where('is_active', true);
        foreach (['store_id', 'crew_member_id', 'crew_role_id', 'engagement_type', 'currency_code'] as $field) {
            $overlap->where($field, $attributes[$field]);
        }
        if ($card !== null) {
            $overlap->whereKeyNot($card->id);
        }
        $overlap->where(function ($query) use ($attributes): void {
            $query->whereNull('effective_to')->orWhere('effective_to', '>=', $attributes['effective_from']);
        });
        if ($attributes['effective_to'] !== null) {
            $overlap->where('effective_from', '<=', $attributes['effective_to']);
        }
        if ($attributes['is_active'] && $overlap->exists()) {
            throw ValidationException::withMessages(['effective_from' => 'An active rate card already covers these dates and criteria.']);
        }
        $card ??= new CrewRateCard(['created_by' => auth()->id()]);
        $card->fill($attributes)->save();

        return $card;
    }
}
