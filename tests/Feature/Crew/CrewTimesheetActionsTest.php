<?php

declare(strict_types=1);

use App\Domain\Crew\Actions\Timesheets\AddCrewTimeEntry;
use App\Domain\Crew\Actions\Timesheets\ApproveCrewTimesheet;
use App\Domain\Crew\Actions\Timesheets\CreateCrewTimesheet;
use App\Domain\Crew\Actions\Timesheets\ExportCrewTimesheetToFinance;
use App\Domain\Crew\Actions\Timesheets\LockCrewTimesheet;
use App\Domain\Crew\Actions\Timesheets\RecalculateCrewTimesheet;
use App\Domain\Crew\Actions\Timesheets\RejectCrewTimesheet;
use App\Domain\Crew\Actions\Timesheets\SubmitCrewTimesheet;
use App\Domain\Crew\Data\Timesheets\AddCrewTimeEntryData;
use App\Domain\Crew\Data\Timesheets\CreateCrewTimesheetData;
use App\Domain\Crew\Data\Timesheets\ExportToFinanceData;
use App\Domain\Crew\Data\Timesheets\RejectCrewTimesheetData;
use App\Domain\Crew\Enums\CrewTimesheetStatus;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewTimesheet;
use App\Domain\Crew\Settings\CrewSettings;
use App\Models\ActionLog;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/** @param list<string> $permissions */
function crewTimesheetActor(array $permissions = []): User
{
    \Pest\Laravel\seed(PermissionSeeder::class);
    $user = User::factory()->create(['is_admin' => true]);
    $user->givePermissionTo($permissions === [] ? ['crew.timesheets.view', 'crew.timesheets.create', 'crew.timesheets.approve', 'crew.timesheets.lock', 'crew.timesheets.export', 'crew.rates.view'] : $permissions);
    \Pest\Laravel\actingAs($user);

    return $user;
}

it('submits approves locks and exports a timesheet without changing payment state', function (): void {
    $this->travelTo('2026-11-02 12:00:00');
    crewTimesheetActor();
    $assignment = CrewAssignment::factory()->confirmed()->create(['min_call_hours' => '4']);
    $created = app(CreateCrewTimesheet::class)($assignment, new CreateCrewTimesheetData('2026-11-02', '2026-11-08', false));
    $sheet = CrewTimesheet::query()->findOrFail($created->id);
    app(AddCrewTimeEntry::class)($sheet, new AddCrewTimeEntryData('2026-11-02T09:00:00Z', '2026-11-02T11:00:00Z'));
    app(SubmitCrewTimesheet::class)($sheet->fresh());
    $approved = app(ApproveCrewTimesheet::class)($sheet->fresh());
    app(LockCrewTimesheet::class)($sheet->fresh());
    $exported = app(ExportCrewTimesheetToFinance::class)($sheet->fresh(), new ExportToFinanceData('AP-CREW-1'));

    expect($approved->total_minutes)->toBe(240);
    expect($approved->total_pay_minor)->toBe(18000);
    expect($exported->status)->toBe('locked');
    $this->assertDatabaseHas('crew_timesheets', ['id' => $sheet->id, 'status' => 'locked', 'finance_reference' => 'AP-CREW-1', 'total_pay_minor' => 18000]);
    expect(ActionLog::query()->where('action', 'crew.timesheet.approved')->exists())->toBeTrue();
});

it('rejects empty submission and leaves the draft untouched', function (): void {
    crewTimesheetActor();
    $sheet = CrewTimesheet::factory()->create();

    expect(function () use ($sheet): void {
        app(SubmitCrewTimesheet::class)($sheet);
    })->toThrow(ValidationException::class);

    expect($sheet->fresh()->status)->toBe(CrewTimesheetStatus::Draft);
    expect(ActionLog::query()->where('action', 'crew.timesheet.submitted')->exists())->toBeFalse();
});

it('rejects stale edits instead of overwriting a newer timesheet version', function (): void {
    crewTimesheetActor();
    $sheet = CrewTimesheet::factory()->create(['period_start' => '2026-11-02', 'period_end' => '2026-11-08']);
    app(AddCrewTimeEntry::class)($sheet, new AddCrewTimeEntryData('2026-11-02T09:00:00Z', '2026-11-02T10:00:00Z'));

    expect(function () use ($sheet): void {
        app(AddCrewTimeEntry::class)($sheet, new AddCrewTimeEntryData('2026-11-02T11:00:00Z', '2026-11-02T12:00:00Z'));
    })->toThrow(ValidationException::class);

    expect($sheet->entries()->count())->toBe(1);
});

it('prevents overlapping time entries across different assignments for the same member', function (): void {
    crewTimesheetActor();
    $sheet = CrewTimesheet::factory()->create(['period_start' => '2026-11-02', 'period_end' => '2026-11-08']);
    app(AddCrewTimeEntry::class)($sheet, new AddCrewTimeEntryData('2026-11-02T09:00:00Z', '2026-11-02T11:00:00Z'));
    $other = CrewTimesheet::factory()->create(['crew_member_id' => $sheet->crew_member_id, 'period_start' => '2026-11-02', 'period_end' => '2026-11-08']);

    expect(function () use ($other): void {
        app(AddCrewTimeEntry::class)($other, new AddCrewTimeEntryData('2026-11-02T10:00:00Z', '2026-11-02T12:00:00Z'));
    })->toThrow(ValidationException::class);

    expect($other->entries()->count())->toBe(0);
});

it('allows rejected entries to be corrected and resubmitted', function (): void {
    crewTimesheetActor();
    $sheet = CrewTimesheet::factory()->withEntry(120)->create();
    $sheet->update(['status' => 'submitted']);
    app(RejectCrewTimesheet::class)($sheet, new RejectCrewTimesheetData('Please include the overrun.'));
    app(SubmitCrewTimesheet::class)($sheet->fresh());

    expect($sheet->fresh()->status)->toBe(CrewTimesheetStatus::Submitted);
    $this->assertDatabaseHas('crew_timesheets', ['id' => $sheet->id, 'rejection_reason' => 'Please include the overrun.']);
});

it('refuses changes and recalculation after approval', function (): void {
    crewTimesheetActor();
    $sheet = CrewTimesheet::factory()->withEntry()->create();
    $sheet->update(['status' => 'approved']);

    expect(function () use ($sheet): void {
        app(RecalculateCrewTimesheet::class)($sheet);
    })->toThrow(AuthorizationException::class);
    expect(function () use ($sheet): void {
        app(AddCrewTimeEntry::class)($sheet, new AddCrewTimeEntryData('2026-11-02T09:00:00Z', '2026-11-02T10:00:00Z'));
    })->toThrow(AuthorizationException::class);
});

it('hides pay totals from users without the separate compensation permission', function (): void {
    crewTimesheetActor(['crew.timesheets.create']);
    $assignment = CrewAssignment::factory()->confirmed()->create();

    $result = app(CreateCrewTimesheet::class)($assignment, new CreateCrewTimesheetData('2026-11-02', '2026-11-08', false));

    expect($result->total_pay_minor)->toBeNull();
});

it('blocks a second fixed-rate timesheet even when periods differ', function (): void {
    crewTimesheetActor();
    $assignment = CrewAssignment::factory()->confirmed()->create(['rate_type' => 'fixed']);
    app(CreateCrewTimesheet::class)($assignment, new CreateCrewTimesheetData('2026-11-02', '2026-11-08', false));

    expect(function () use ($assignment): void {
        app(CreateCrewTimesheet::class)($assignment, new CreateCrewTimesheetData('2026-11-09', '2026-11-15', false));
    })->toThrow(ValidationException::class);
});

it('prevents an unprivileged caller from creating a timesheet', function (): void {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $assignment = CrewAssignment::factory()->confirmed()->create();

    expect(function () use ($assignment): void {
        app(CreateCrewTimesheet::class)($assignment, new CreateCrewTimesheetData('2026-11-02', '2026-11-08', false));
    })->toThrow(AuthorizationException::class);

    $this->assertDatabaseCount('crew_timesheets', 0);
});

it('counts previously approved hours across other assignments towards weekly overtime', function (): void {
    crewTimesheetActor();
    $previous = CrewTimesheet::factory()->create(['period_start' => '2026-11-02', 'period_end' => '2026-11-06']);
    foreach ([2, 3, 4, 5, 6] as $day) {
        $previous->entries()->create(['kind' => 'work', 'work_date' => "2026-11-0{$day}", 'started_at' => "2026-11-0{$day} 09:00:00", 'ended_at' => "2026-11-0{$day} 17:00:00", 'break_minutes' => 0, 'minutes' => 480]);
    }
    $previous->update(['status' => 'approved']);
    $assignment = CrewAssignment::factory()->confirmed()->create(['crew_member_id' => $previous->crew_member_id, 'rate_amount_minor' => 6000, 'allowances_snapshot' => ['_policy' => array_replace((new CrewSettings)->defaults(), ['weekend_multiplier' => '1.00'])]]);
    $created = app(CreateCrewTimesheet::class)($assignment, new CreateCrewTimesheetData('2026-11-07', '2026-11-07', false));
    $sheet = CrewTimesheet::query()->findOrFail($created->id);
    app(AddCrewTimeEntry::class)($sheet, new AddCrewTimeEntryData('2026-11-07T09:00:00Z', '2026-11-07T17:00:00Z'));

    $result = app(SubmitCrewTimesheet::class)($sheet->fresh());

    expect($result->total_pay_minor)->toBe(72000);
});

it('blocks backdated submissions that would invalidate a later frozen pay week', function (): void {
    crewTimesheetActor();
    $later = CrewTimesheet::factory()->create(['period_start' => '2026-11-07', 'period_end' => '2026-11-07']);
    $later->entries()->create(['kind' => 'work', 'work_date' => '2026-11-07', 'started_at' => '2026-11-07 09:00:00', 'ended_at' => '2026-11-07 17:00:00', 'break_minutes' => 0, 'minutes' => 480]);
    $later->update(['status' => 'approved']);
    $earlier = CrewTimesheet::factory()->create(['crew_member_id' => $later->crew_member_id, 'period_start' => '2026-11-02', 'period_end' => '2026-11-02']);
    app(AddCrewTimeEntry::class)($earlier, new AddCrewTimeEntryData('2026-11-02T09:00:00Z', '2026-11-02T17:00:00Z'));

    expect(function () use ($earlier): void {
        app(SubmitCrewTimesheet::class)($earlier->fresh());
    })->toThrow(ValidationException::class);

    expect($earlier->fresh()->status)->toBe(CrewTimesheetStatus::Draft);
});

it('keeps approved totals and entries immutable through direct model writes', function (): void {
    crewTimesheetActor();
    $sheet = CrewTimesheet::factory()->withEntry(480)->create(['total_pay_minor' => 36000]);
    $entry = $sheet->entries()->firstOrFail();
    $sheet->update(['status' => 'approved']);

    expect(function () use ($sheet): void {
        $sheet->update(['total_pay_minor' => 1]);
    })->toThrow(ValidationException::class);
    expect(function () use ($entry): void {
        $entry->update(['amount_minor' => 1]);
    })->toThrow(ValidationException::class);
    expect(function () use ($entry): void {
        $entry->delete();
    })->toThrow(ValidationException::class);

    $this->assertDatabaseHas('crew_timesheets', ['id' => $sheet->id, 'total_pay_minor' => 36000]);
});

it('pays an assignment allowance only once across nonoverlapping timesheet periods', function (): void {
    crewTimesheetActor();
    $assignment = CrewAssignment::factory()->confirmed()->create(['rate_amount_minor' => 6000, 'allowances_snapshot' => [['code' => 'KIT', 'amount_minor' => 1000, 'per' => 'assignment', 'currency' => 'GBP']]]);
    $first = app(CreateCrewTimesheet::class)($assignment, new CreateCrewTimesheetData('2026-11-02', '2026-11-02', false));
    $sheet = CrewTimesheet::query()->findOrFail($first->id);
    app(AddCrewTimeEntry::class)($sheet, new AddCrewTimeEntryData('2026-11-02T09:00:00Z', '2026-11-02T10:00:00Z'));
    app(SubmitCrewTimesheet::class)($sheet->fresh());
    $second = app(CreateCrewTimesheet::class)($assignment, new CreateCrewTimesheetData('2026-11-03', '2026-11-03', false));
    $sheet = CrewTimesheet::query()->findOrFail($second->id);
    app(AddCrewTimeEntry::class)($sheet, new AddCrewTimeEntryData('2026-11-03T09:00:00Z', '2026-11-03T10:00:00Z'));

    $result = app(SubmitCrewTimesheet::class)($sheet->fresh());

    expect($result->total_pay_minor)->toBe(6000);
});
