<?php

declare(strict_types=1);

use App\Domain\Crew\Actions\Assignments\CancelCrewAssignment;
use App\Domain\Crew\Actions\Assignments\RecalculateCrewAssignmentCost;
use App\Domain\Crew\Actions\Requests\CancelCrewRequest;
use App\Domain\Crew\Actions\Timesheets\AddCrewTimeEntry;
use App\Domain\Crew\Actions\Timesheets\CreateCrewTimesheet;
use App\Domain\Crew\Actions\Timesheets\SubmitCrewTimesheet;
use App\Domain\Crew\Data\Assignments\CancelCrewAssignmentData;
use App\Domain\Crew\Data\Requests\CancelCrewRequestData;
use App\Domain\Crew\Data\Timesheets\AddCrewTimeEntryData;
use App\Domain\Crew\Data\Timesheets\CreateCrewTimesheetData;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewShift;
use App\Domain\Crew\Models\CrewTimesheet;
use App\Domain\Crew\Queries\CrewCostForecastQuery;
use App\Domain\Crew\Services\CrewCancellationCompensation;
use App\Models\ActionLog;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Validation\ValidationException;

function crewCancellationActor(): void
{
    \Pest\Laravel\seed(PermissionSeeder::class);
    $user = User::factory()->create(['is_admin' => true]);
    $user->givePermissionTo(['crew.assignments.cancel', 'crew.assignments.view', 'crew.requests.cancel', 'crew.rates.view', 'crew.reports.view', 'crew.timesheets.create']);
    \Pest\Laravel\actingAs($user);
}

it('records a late cancelled call minimum without inventing worked time', function (): void {
    $this->travelTo('2026-11-02 09:00:00');
    crewCancellationActor();
    $assignment = CrewAssignment::factory()->confirmed()->create(['min_call_hours' => '4', 'cost_estimate_minor' => 50000]);
    CrewShift::factory()->forAssignment($assignment)->confirmed()->create(['starts_at' => '2026-11-03 09:00:00', 'ends_at' => '2026-11-03 17:00:00']);

    app(CancelCrewAssignment::class)($assignment, new CancelCrewAssignmentData('Event cancelled.'));

    $assignment->refresh();
    expect($assignment->cost_estimate_minor)->toBe(18000);
    expect($assignment->cost_estimate_breakdown['cancellation'])->toMatchArray(['status' => 'due', 'minutes' => 240, 'amount_minor' => 18000, 'currency_code' => 'GBP']);
    expect($assignment->cost_estimate_breakdown['planned_estimate_minor'])->toBe(50000);
    $this->assertDatabaseCount('crew_timesheets', 0);
    $this->assertDatabaseCount('crew_time_entries', 0);
    expect(ActionLog::query()->where('action', 'crew.assignment.cancelled')->exists())->toBeTrue();
});

it('pays the call minimum at the inclusive notice boundary and none outside it', function (string $start, string $end, int $expected): void {
    $this->travelTo('2026-11-02 09:00:00');
    $assignment = CrewAssignment::factory()->confirmed()->create(['min_call_hours' => '4']);
    CrewShift::factory()->forAssignment($assignment)->confirmed()->create(['starts_at' => $start, 'ends_at' => $end]);

    $result = app(CrewCancellationCompensation::class)->snapshot($assignment);

    expect($result['amount_minor'])->toBe($expected);
})->with([
    'exactly 48 hours' => ['2026-11-04 09:00:00', '2026-11-04 17:00:00', 18000],
    '49 hours notice' => ['2026-11-04 10:00:00', '2026-11-04 18:00:00', 0],
]);

it('pays one minimum per affected local call date rather than per shift', function (): void {
    $this->travelTo('2026-11-02 09:00:00');
    $assignment = CrewAssignment::factory()->confirmed()->create(['min_call_hours' => '4']);
    CrewShift::factory()->forAssignment($assignment)->confirmed()->create(['starts_at' => '2026-11-03 09:00:00', 'ends_at' => '2026-11-03 11:00:00']);
    CrewShift::factory()->forAssignment($assignment)->confirmed()->create(['starts_at' => '2026-11-03 16:00:00', 'ends_at' => '2026-11-03 18:00:00']);
    CrewShift::factory()->forAssignment($assignment)->confirmed()->create(['starts_at' => '2026-11-04 08:00:00', 'ends_at' => '2026-11-04 10:00:00']);

    $result = app(CrewCancellationCompensation::class)->snapshot($assignment);

    expect($result['amount_minor'])->toBe(36000);
    expect($result['minutes'])->toBe(480);
    expect($result['calls'])->toHaveCount(2);
});

it('does not invent a fixed fee or unagreed minimum for cancellation', function (string $status, string $type, string $minimum, int $notice): void {
    $this->travelTo('2026-11-02 09:00:00');
    $assignment = CrewAssignment::factory()->create(['status' => $status, 'rate_type' => $type, 'min_call_hours' => $minimum, 'allowances_snapshot' => ['_policy' => ['cancellation_pay_hours' => $notice]]]);
    CrewShift::factory()->forAssignment($assignment)->confirmed()->create(['starts_at' => '2026-11-03 09:00:00', 'ends_at' => '2026-11-03 17:00:00']);

    $result = app(CrewCancellationCompensation::class)->snapshot($assignment);

    expect($result['amount_minor'])->toBe(0);
})->with([
    'unconfirmed proposal' => ['proposed', 'hourly', '4', 48],
    'no agreed minimum' => ['confirmed', 'hourly', '0', 48],
    'fixed engagement has no hourly call minimum' => ['confirmed', 'fixed', '4', 48],
    'disabled cancellation policy' => ['confirmed', 'hourly', '4', 0],
]);

it('uses the snapshotted hourly equivalent for a daily rate call minimum', function (): void {
    $this->travelTo('2026-11-02 09:00:00');
    $assignment = CrewAssignment::factory()->confirmed()->create(['rate_type' => 'daily', 'rate_amount_minor' => 35000, 'daily_call_hours' => '10', 'min_call_hours' => '4']);
    CrewShift::factory()->forAssignment($assignment)->confirmed()->create(['starts_at' => '2026-11-03 09:00:00', 'ends_at' => '2026-11-03 17:00:00']);

    $result = app(CrewCancellationCompensation::class)->snapshot($assignment);

    expect($result['amount_minor'])->toBe(14000);
});

it('preserves cancellation entitlements through request cancellation and cost recalculation', function (): void {
    $this->travelTo('2026-11-02 09:00:00');
    crewCancellationActor();
    $assignment = CrewAssignment::factory()->confirmed()->create(['min_call_hours' => '4']);
    CrewShift::factory()->forAssignment($assignment)->confirmed()->create(['starts_at' => '2026-11-03 09:00:00', 'ends_at' => '2026-11-03 17:00:00']);
    app(CancelCrewRequest::class)($assignment->request, new CancelCrewRequestData('Project cancelled.'));
    app(RecalculateCrewAssignmentCost::class)($assignment->fresh());

    $forecast = app(CrewCostForecastQuery::class)->forProject($assignment->project);

    expect($forecast->cancellation_minor)->toBe(['GBP' => 18000]);
    expect($assignment->fresh()->cost_estimate_minor)->toBe(18000);
    $this->assertDatabaseCount('crew_time_entries', 0);
});

it('keeps completed work payable without paying its call minimum twice', function (): void {
    $this->travelTo('2026-11-02 15:00:00');
    crewCancellationActor();
    $assignment = CrewAssignment::factory()->confirmed()->create(['min_call_hours' => '4']);
    $completed = CrewShift::factory()->forAssignment($assignment)->completed()->create(['starts_at' => '2026-11-02 09:00:00', 'ends_at' => '2026-11-02 11:00:00']);
    $cancelled = CrewShift::factory()->forAssignment($assignment)->confirmed()->create(['starts_at' => '2026-11-02 16:00:00', 'ends_at' => '2026-11-02 18:00:00']);
    app(CancelCrewAssignment::class)($assignment, new CancelCrewAssignmentData('Afternoon show cancelled.'));

    $result = app(CreateCrewTimesheet::class)($assignment->fresh(), new CreateCrewTimesheetData('2026-11-02', '2026-11-02'));

    expect($assignment->fresh()->cost_estimate_breakdown['cancellation']['amount_minor'])->toBe(0);
    expect($result->total_pay_minor)->toBe(18000);
    expect($result->entries)->toHaveCount(1);
    expect($result->entries[0]->crew_shift_id)->toBe($completed->id);
    $sheet = CrewTimesheet::query()->findOrFail($result->id);
    expect(function () use ($sheet, $cancelled): void {
        app(AddCrewTimeEntry::class)($sheet, new AddCrewTimeEntryData('2026-11-02T16:00:00Z', '2026-11-02T18:00:00Z', crew_shift_id: $cancelled->id));
    })->toThrow(ValidationException::class);
});

it('preserves the cancellation ledger against direct mutation after cancellation', function (): void {
    $this->travelTo('2026-11-02 09:00:00');
    crewCancellationActor();
    $assignment = CrewAssignment::factory()->confirmed()->create(['min_call_hours' => '4']);
    CrewShift::factory()->forAssignment($assignment)->confirmed()->create(['starts_at' => '2026-11-03 09:00:00', 'ends_at' => '2026-11-03 17:00:00']);
    app(CancelCrewAssignment::class)($assignment, new CancelCrewAssignmentData('Cancelled.'));

    expect(function () use ($assignment): void {
        $assignment->fresh()->update(['cost_estimate_minor' => 1]);
    })->toThrow(ValidationException::class);
    expect(function () use ($assignment): void {
        $assignment->fresh()->update(['cost_estimate_breakdown' => []]);
    })->toThrow(ValidationException::class);

    expect($assignment->fresh()->cost_estimate_breakdown['cancellation']['amount_minor'])->toBe(18000);
});

it('retains the known confirmed call minimum before shifts are resolved', function (): void {
    $this->travelTo('2026-11-02 09:00:00');
    $assignment = CrewAssignment::factory()->confirmed()->create(['min_call_hours' => '4']);
    $assignment->line->update(['starts_at' => '2026-11-03 09:00:00', 'ends_at' => '2026-11-03 17:00:00']);

    $result = app(CrewCancellationCompensation::class)->snapshot($assignment);

    expect($result['amount_minor'])->toBe(18000);
    expect($result['calls'][0]['source'])->toBe('request_line');
});

it('blocks seeded future work from being submitted in addition to cancellation compensation', function (): void {
    $this->travelTo('2026-11-02 09:00:00');
    crewCancellationActor();
    $assignment = CrewAssignment::factory()->confirmed()->create(['min_call_hours' => '4']);
    CrewShift::factory()->forAssignment($assignment)->confirmed()->create(['starts_at' => '2026-11-03 09:00:00', 'ends_at' => '2026-11-03 17:00:00']);
    $draft = app(CreateCrewTimesheet::class)($assignment, new CreateCrewTimesheetData('2026-11-03', '2026-11-03'));
    app(CancelCrewAssignment::class)($assignment->fresh(), new CancelCrewAssignmentData('Cancelled.'));
    $sheet = CrewTimesheet::query()->findOrFail($draft->id);

    expect(function () use ($sheet): void {
        app(SubmitCrewTimesheet::class)($sheet);
    })->toThrow(ValidationException::class);

    expect($assignment->fresh()->cost_estimate_breakdown['cancellation']['amount_minor'])->toBe(18000);
    expect($sheet->fresh()->status->value)->toBe('draft');
});
