<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Rates;

use App\Domain\Crew\Data\Rates\ResolveCrewRateData;
use App\Domain\Crew\Data\Rates\ResolvedCrewRateData;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewRateCard;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ResolveCrewRate
{
    public function __invoke(ResolveCrewRateData $data): ResolvedCrewRateData
    {
        abort_unless(Gate::any(['crew.rates.view', 'crew.assignments.create']), 403);
        abort_unless(auth()->user()?->canAccessStore($data->store_id), 403);
        Validator::make($data->toArray(), ['effective_date' => ['required', 'date_format:Y-m-d'], 'currency_code' => ['required', 'regex:/^[A-Z]{3}$/'], 'crew_role_id' => ['required', 'exists:crew_roles,id']])->validate();
        $member = CrewMember::query()->findOrFail($data->crew_member_id);
        $field = $data->rate_type->value.'_rate_minor';
        $card = CrewRateCard::query()->where('is_active', true)->where('currency_code', $data->currency_code)
            ->where('effective_from', '<=', $data->effective_date)
            ->where(function ($query) use ($data): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $data->effective_date);
            })
            ->where(function ($query) use ($data): void {
                $query->whereNull('store_id')->orWhere('store_id', $data->store_id);
            })
            ->where(function ($query) use ($data): void {
                $query->whereNull('crew_member_id')->orWhere('crew_member_id', $data->crew_member_id);
            })
            ->where(function ($query) use ($data): void {
                $query->whereNull('crew_role_id')->orWhere('crew_role_id', $data->crew_role_id);
            })
            ->where(function ($query) use ($member): void {
                $query->whereNull('engagement_type')->orWhere('engagement_type', $member->engagement_type->value);
            })
            ->whereNotNull($field)
            ->orderByRaw('CASE WHEN crew_member_id IS NOT NULL THEN 1 ELSE 0 END DESC')
            ->orderByRaw('CASE WHEN crew_role_id IS NOT NULL THEN 1 ELSE 0 END DESC')
            ->orderByRaw('CASE WHEN engagement_type IS NOT NULL THEN 1 ELSE 0 END DESC')
            ->orderByRaw('CASE WHEN store_id IS NOT NULL THEN 1 ELSE 0 END DESC')
            ->orderByDesc('effective_from')->orderByDesc('id')->first();
        if ($card === null) {
            throw ValidationException::withMessages(['rate_card_id' => 'No active rate matches the member, role, currency and date.']);
        }
        $allowances = [];
        if ($card->per_diem_minor !== null) {
            $allowances[] = ['code' => 'PER_DIEM', 'label' => 'Per diem', 'amount_minor' => $card->per_diem_minor, 'currency' => $card->currency_code, 'per' => 'day'];
        }
        $policy = settings()->group('crew');
        foreach (['weekend_multiplier', 'night_multiplier', 'holiday_multiplier', 'travel_rate_minor'] as $key) {
            if ($card->{$key} !== null) {
                $policy[$key] = $card->{$key};
            }
        }
        $allowances['_policy'] = $policy;

        return new ResolvedCrewRateData($card->id, $data->rate_type, $card->{$field}, $card->currency_code, $card->overtime_multiplier, $card->min_call_hours ?? '0', $card->daily_call_hours ?? (string) settings('crew.daily_call_hours', '10'), $allowances);
    }
}
