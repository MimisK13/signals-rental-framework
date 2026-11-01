<?php

declare(strict_types=1);

namespace App\Domain\Crew\Services;

use App\Domain\Crew\Enums\CrewExpenseCategory;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewExpense;
use Brick\Money\Currency;
use Brick\Money\Exception\UnknownCurrencyException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CrewExpenseEditor
{
    public function lock(CrewExpense $expense, string $ability): CrewExpense
    {
        $expense = CrewExpense::query()->lockForUpdate()->findOrFail($expense->id);

        Gate::authorize($ability, $expense);

        return $expense;
    }

    /** @param array<string,mixed> $attributes */
    public function fill(CrewExpense $expense, array $attributes): void
    {
        $attributes = Validator::make($attributes, [
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'crew_assignment_id' => ['nullable', 'integer'],
            'category' => ['required', Rule::enum(CrewExpenseCategory::class)],
            'incurred_on' => ['required', 'date_format:Y-m-d'],
            'description' => ['required', 'string', 'max:255'],
            'amount_minor' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'currency_code' => ['required', 'regex:/^[A-Z]{3}$/'],
            'exchange_rate' => ['required', 'regex:/^\d{1,8}(\.\d{1,6})?$/', 'numeric', 'gt:0'],
            'billable_to_client' => ['boolean'],
            'markup_percent' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
        ])->validate();
        try {
            Currency::of($attributes['currency_code']);
        } catch (UnknownCurrencyException) {
            throw ValidationException::withMessages(['currency_code' => 'Choose a supported ISO currency code.']);
        }
        abort_unless(auth()->user()?->canAccessStore($attributes['store_id']), 403);
        if ($attributes['currency_code'] === settings('company.base_currency', 'GBP') && bccomp($attributes['exchange_rate'], '1', 6) !== 0) {
            throw ValidationException::withMessages(['exchange_rate' => 'Base currency expenses must use an exchange rate of one.']);
        }
        if ($attributes['crew_assignment_id'] !== null) {
            $assignment = CrewAssignment::query()->find($attributes['crew_assignment_id']);
            if ($assignment === null || $assignment->crew_member_id !== $expense->crew_member_id || $assignment->store_id !== $attributes['store_id'] || $assignment->project_id !== $attributes['project_id']) {
                throw ValidationException::withMessages(['crew_assignment_id' => 'The assignment must belong to the same member, project and store.']);
            }
        }
        $expense->fill($attributes);
        $expense->amount_base_minor = (int) bcadd(bcmul((string) $expense->amount_minor, $attributes['exchange_rate'], 6), '0.5', 0);
    }
}
