<?php

declare(strict_types=1);

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\AuditLog\Models\ActionLog;
use App\Domain\Crew\Console\ExpireCrewInvitations;
use App\Domain\Crew\Console\ScanCrewQualifications;
use App\Domain\Crew\Console\ScanCrewShortages;
use App\Domain\Crew\Data\Planning\UtilisationFilterData;
use App\Domain\Crew\Data\Planning\WorkHistoryFilterData;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewAvailability;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewMemberQualification;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Models\CrewRequestLine;
use App\Domain\Crew\Models\CrewShift;
use App\Domain\Crew\Models\CrewTimesheet;
use App\Domain\Crew\Queries\CrewCostForecastQuery;
use App\Domain\Crew\Queries\CrewExpiringQualificationQuery;
use App\Domain\Crew\Queries\CrewUtilisationQuery;
use App\Domain\Crew\Queries\CrewWorkHistoryQuery;
use Illuminate\Auth\Access\AuthorizationException;

function crewPlanningUser(): User
{
    \Pest\Laravel\freezeTime();
    \Pest\Laravel\seed(PermissionSeeder::class);
    $user = User::factory()->create(['is_admin' => true]);
    $user->givePermissionTo(['crew.reports.view', 'crew.rates.view', 'crew.view']);
    \Pest\Laravel\actingAs($user);
    settings()->set('modules.crew', true, 'boolean');

    return $user;
}

it('groups forecast and approved cost by currency without adding unlike money', function (): void {
    crewPlanningUser();
    $request = CrewRequest::factory()->create();
    CrewAssignment::factory()->create(['crew_request_id' => $request->id, 'project_id' => $request->project_id, 'store_id' => $request->store_id, 'status' => 'confirmed', 'currency_code' => 'GBP', 'cost_estimate_minor' => 12000]);
    CrewAssignment::factory()->create(['crew_request_id' => $request->id, 'project_id' => $request->project_id, 'store_id' => $request->store_id, 'status' => 'proposed', 'currency_code' => 'EUR', 'cost_estimate_minor' => 9000]);
    CrewTimesheet::factory()->approved()->create(['project_id' => $request->project_id, 'store_id' => $request->store_id, 'currency_code' => 'GBP', 'total_pay_minor' => 11000]);

    $forecast = app(CrewCostForecastQuery::class)->forProject($request->project);

    expect($forecast->currencies)->toBe([
        'EUR' => ['confirmed_minor' => 0, 'proposed_minor' => 9000, 'approved_minor' => 0, 'unestimated_assignments' => 0],
        'GBP' => ['confirmed_minor' => 12000, 'proposed_minor' => 0, 'approved_minor' => 11000, 'unestimated_assignments' => 0],
    ]);
});

it('denies monetary forecasts without rate visibility', function (): void {
    $user = crewPlanningUser();
    $user->revokePermissionTo('crew.rates.view');
    $request = CrewRequest::factory()->create();

    expect(fn () => app(CrewCostForecastQuery::class)->forProject($request->project))->toThrow(AuthorizationException::class);
});

it('identifies assignments without an estimate rather than presenting them as a free quote', function (): void {
    crewPlanningUser();
    $assignment = CrewAssignment::factory()->create(['currency_code' => 'GBP', 'cost_estimate_minor' => null]);

    $forecast = app(CrewCostForecastQuery::class)->forProject($assignment->project);

    expect($forecast->currencies['GBP']['unestimated_assignments'])->toBe(1);
    expect($forecast->currencies['GBP']['proposed_minor'])->toBe(0);
});

it('subtracts merged availability from capacity and counts booked and approved worked minutes separately', function (): void {
    crewPlanningUser();
    $member = CrewMember::factory()->create();
    CrewShift::factory()->confirmed()->create(['crew_member_id' => $member->id, 'starts_at' => '2027-02-10 09:00:00', 'ends_at' => '2027-02-10 13:00:00', 'break_minutes' => 30]);
    CrewAvailability::factory()->create(['crew_member_id' => $member->id, 'status' => 'approved', 'starts_at' => '2027-02-10 13:00:00', 'ends_at' => '2027-02-10 16:00:00']);
    CrewAvailability::factory()->create(['crew_member_id' => $member->id, 'status' => 'approved', 'starts_at' => '2027-02-10 15:00:00', 'ends_at' => '2027-02-10 17:00:00']);
    $sheet = CrewTimesheet::factory()->create(['crew_member_id' => $member->id]);
    $sheet->entries()->create(['kind' => 'work', 'work_date' => '2027-02-10', 'started_at' => '2027-02-10 09:00:00', 'ended_at' => '2027-02-10 12:00:00', 'break_minutes' => 0, 'minutes' => 180, 'currency_code' => 'GBP']);
    $sheet->update(['status' => 'approved']);

    $report = app(CrewUtilisationQuery::class)->forPeriod(new UtilisationFilterData('2027-02-10 09:00:00', '2027-02-10 17:00:00', $member->id));

    expect($report->rows[0]->capacity_minutes)->toBe(240);
    expect($report->rows[0]->booked_minutes)->toBe(210);
    expect($report->rows[0]->worked_minutes)->toBe(180);
    expect($report->rows[0]->utilisation_percent)->toBe(87.5);
});

it('returns work history with inherited periods and without compensation or private notes', function (): void {
    crewPlanningUser();
    $request = CrewRequest::factory()->create(['starts_at' => '2027-02-10 09:00:00', 'ends_at' => '2027-02-10 17:00:00']);
    $line = CrewRequestLine::factory()->create(['crew_request_id' => $request->id]);
    $member = CrewMember::factory()->create();
    $assignment = CrewAssignment::factory()->forLine($line)->forMember($member)->create();

    $history = app(CrewWorkHistoryQuery::class)->forMember($member->id, new WorkHistoryFilterData('2027-02-01', '2027-03-01'));

    expect($history->assignments)->toHaveCount(1);
    expect($history->assignments[0]['assignment_id'])->toBe($assignment->id);
    expect($history->assignments[0]['starts_at'])->toBe('2027-02-10T09:00:00+00:00');
    expect($history->assignments[0])->not->toHaveKeys(['rate_amount_minor', 'internal_notes']);
});

it('finds expired and soon expiring qualifications only for active members', function (): void {
    crewPlanningUser();
    $expired = CrewMemberQualification::factory()->expired()->create();
    $soon = CrewMemberQualification::factory()->expiring()->create();
    CrewMemberQualification::factory()->create();
    $inactive = CrewMember::factory()->create(['status' => 'inactive']);
    CrewMemberQualification::factory()->expired()->create(['crew_member_id' => $inactive->id]);

    $qualifications = app(CrewExpiringQualificationQuery::class)->dueWithin(30);

    expect($qualifications->pluck('id')->all())->toBe([$expired->id, $soon->id]);
});

it('expires invitations once while preserving confirmed assignments and releasing scheduling keys', function (): void {
    crewPlanningUser();
    $expired = CrewAssignment::factory()->invited()->create(['invited_at' => now()->subHours(80)]);
    $confirmed = CrewAssignment::factory()->confirmed()->create();
    $shift = CrewShift::factory()->forAssignment($expired)->create();

    \Pest\Laravel\artisan(ExpireCrewInvitations::class)->assertSuccessful();
    \Pest\Laravel\artisan(ExpireCrewInvitations::class)->assertSuccessful();

    expect($expired->refresh()->status->value)->toBe('cancelled');
    expect($expired->active_assignment_key)->toBeNull();
    expect($confirmed->refresh()->status->value)->toBe('confirmed');
    expect($shift->refresh()->status->value)->toBe('cancelled');
    \Pest\Laravel\assertDatabaseCount('action_logs', 2);
});

it('emits at most one shortage notification per line per day', function (): void {
    crewPlanningUser();
    $request = CrewRequest::factory()->open()->create(['starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(3)]);
    $line = CrewRequestLine::factory()->create(['crew_request_id' => $request->id]);

    \Pest\Laravel\artisan(ScanCrewShortages::class)->assertSuccessful();
    \Pest\Laravel\artisan(ScanCrewShortages::class)->assertSuccessful();

    \Pest\Laravel\assertDatabaseHas('action_logs', ['action' => 'crew.shortage.detected', 'auditable_id' => $line->id]);
    expect(ActionLog::where('action', 'crew.shortage.detected')->count())->toBe(1);
});

it('emits qualification expiry campaigns once for each qualification expiry date', function (): void {
    crewPlanningUser();
    $record = CrewMemberQualification::factory()->expiring()->create();

    \Pest\Laravel\artisan(ScanCrewQualifications::class)->assertSuccessful();
    \Pest\Laravel\artisan(ScanCrewQualifications::class)->assertSuccessful();

    expect(ActionLog::where('action', 'crew.qualification.expiring')->count())->toBe(1);
    $record->update(['expires_at' => today()->subDay()]);
    \Pest\Laravel\artisan(ScanCrewQualifications::class)->assertSuccessful();
    expect(ActionLog::where('action', 'crew.qualification.expired')->count())->toBe(1);
});

it('leaves invitations untouched when the Crew module is disabled', function (): void {
    crewPlanningUser();
    settings()->set('modules.crew', false, 'boolean');
    $assignment = CrewAssignment::factory()->invited()->create(['invited_at' => now()->subHours(80)]);

    \Pest\Laravel\artisan(ExpireCrewInvitations::class)->assertSuccessful();

    expect($assignment->refresh()->status->value)->toBe('invited');
});
