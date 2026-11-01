<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Assignments;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Actions\Rates\ResolveCrewRate;
use App\Domain\Crew\Data\Assignments\CrewAssignmentData;
use App\Domain\Crew\Data\Assignments\ProposeCrewAssignmentData;
use App\Domain\Crew\Data\Rates\ResolveCrewRateData;
use App\Domain\Crew\Enums\CrewAssignmentStatus;
use App\Domain\Crew\Enums\CrewRequestStatus;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Models\CrewRequestLine;
use App\Domain\Crew\Queries\CrewConflictQuery;
use App\Domain\Crew\Queries\CrewRequirementQuery;
use App\Domain\Crew\Services\CrewAudit;
use App\Services\CurrencyService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class ProposeCrewAssignment
{
    public function __invoke(CrewRequestLine $line, ProposeCrewAssignmentData $data, ?string $overrideReason = null): CrewAssignmentData
    {
        Gate::authorize('create', CrewAssignment::class);
        Gate::allowIf(auth()->user()->canAccessStore($line->request->store_id));

        return DB::transaction(function () use ($line, $data, $overrideReason): CrewAssignmentData {
            $member = StaffingGuard::memberLock($data->crew_member_id);
            $request = CrewRequest::query()->lockForUpdate()->findOrFail($line->crew_request_id);
            $line = CrewRequestLine::query()->lockForUpdate()->findOrFail($line->id);
            StaffingGuard::require(in_array($request->status, [CrewRequestStatus::Open, CrewRequestStatus::PartiallyFilled], true), 'status', 'The request must be open for staffing.');
            StaffingGuard::require($line->assignments()->where('status', 'confirmed')->count() < $line->quantity, 'quantity', 'This role line is already filled.');
            StaffingGuard::require(! $line->assignments()->where('crew_member_id', $member->id)->whereIn('status', ['proposed', 'invited', 'confirmed'])->exists(), 'crew_member_id', 'The member already has an active assignment for this role line.');
            $report = app(CrewConflictQuery::class)->detect($member->id, CarbonImmutable::parse($line->effectiveStartsAt()), CarbonImmutable::parse($line->effectiveEndsAt()));
            $requirements = app(CrewRequirementQuery::class)->forLine($member, $line);
            $report->hard = [...$report->hard, ...$requirements->hard];
            $report->warnings = [...$report->warnings, ...$requirements->warnings];
            StaffingGuard::conflicts($report, $overrideReason);
            $rate = app(ResolveCrewRate::class)(new ResolveCrewRateData(
                crew_member_id: $member->id, crew_role_id: $line->crew_role_id, store_id: $request->store_id, effective_date: $line->effectiveStartsAt()->toDateString(), currency_code: $data->currency_code,
            ));
            try {
                $exchangeRate = app(CurrencyService::class)->getRate($rate->currency_code, settings('company.base_currency', 'GBP'), Carbon::parse($line->effectiveStartsAt()));
            } catch (RuntimeException $exception) {
                throw ValidationException::withMessages(['currency_code' => $exception->getMessage()]);
            }
            $assignment = CrewAssignment::create([
                'crew_request_id' => $request->id, 'crew_request_line_id' => $line->id, 'crew_member_id' => $member->id, 'project_id' => $request->project_id, 'store_id' => $request->store_id,
                'status' => CrewAssignmentStatus::Proposed, 'active_assignment_key' => CrewAssignment::activeKeyFor($line->id, $member->id),
                'rate_card_id' => $rate->rate_card_id, 'rate_type' => $rate->rate_type, 'rate_amount_minor' => $rate->rate_amount_minor, 'currency_code' => $rate->currency_code,
                'exchange_rate_to_base' => bcround($exchangeRate, 6),
                'overtime_multiplier' => $rate->overtime_multiplier, 'min_call_hours' => $rate->min_call_hours, 'daily_call_hours' => $rate->daily_call_hours,
                'allowances_snapshot' => $rate->allowances_snapshot, 'created_by' => auth()->id(),
            ]);
            app(CrewAudit::class)->record('crew.assignment.proposed', $assignment, ['override_reason' => $overrideReason, 'warnings' => $report->warnings]);

            return CrewAssignmentData::fromModel($assignment);
        });
    }
}
