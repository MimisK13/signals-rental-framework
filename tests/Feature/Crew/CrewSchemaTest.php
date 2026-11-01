<?php

declare(strict_types=1);

use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewRequestLine;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

it('creates every crew table', function (string $table): void {
    expect(Schema::hasTable($table))->toBeTrue();
})->with([
    'crew_roles',
    'crew_skills',
    'crew_qualifications',
    'crew_members',
    'crew_member_roles',
    'crew_member_skills',
    'crew_member_qualifications',
    'crew_role_requirements',
    'crew_rate_cards',
    'crew_requests',
    'crew_request_lines',
    'crew_request_line_requirements',
    'crew_assignments',
    'crew_shifts',
    'crew_availabilities',
    'crew_timesheets',
    'crew_time_entries',
    'crew_expenses',
    'crew_shift_breaks',
]);

it('loads crew migrations from the domain, not the application migrations folder', function (): void {
    $domainPath = app_path('Domain/Crew/Database/Migrations');
    $registeredPaths = array_map(
        static fn (string $path): string => str_replace('\\', '/', $path),
        app('migrator')->paths(),
    );

    expect(is_dir($domainPath))->toBeTrue();
    $domainMigrations = glob($domainPath.'/*_*.php');
    if ($domainMigrations === false) {
        throw new RuntimeException('Cannot inspect domain migrations.');
    }
    expect(count($domainMigrations))->toBe(19);
    $applicationCrewMigrations = glob(database_path('migrations/*crew_*.php'));
    if ($applicationCrewMigrations === false) {
        throw new RuntimeException('Cannot inspect application migrations.');
    }
    expect(count($applicationCrewMigrations))->toBe(0);
    expect($registeredPaths)->toContain(str_replace('\\', '/', $domainPath));
});

it('stores money as integer minor units and statuses as strings', function (): void {
    $assignmentColumns = Schema::getColumnListing('crew_assignments');
    $timesheetColumns = Schema::getColumnListing('crew_timesheets');

    expect($assignmentColumns)->toContain('rate_amount_minor')
        ->and($assignmentColumns)->toContain('currency_code')
        ->and($assignmentColumns)->not->toContain('rate_amount')
        ->and($timesheetColumns)->toContain('total_pay_minor')
        ->and($timesheetColumns)->toContain('total_minutes');
});

it('allows a crew member without a linked user', function (): void {
    $member = CrewMember::factory()->withoutUser()->create();

    expect($member->user_id)->toBeNull()
        ->and($member->user)->toBeNull()
        ->and($member->isActive())->toBeTrue();
});

it('prevents duplicate active assignments for the same line and member', function (): void {
    $line = CrewRequestLine::factory()->create();
    $member = CrewMember::factory()->create();

    CrewAssignment::factory()->forLine($line)->forMember($member)->create();

    expect(static fn () => CrewAssignment::factory()->forLine($line)->forMember($member)->create())
        ->toThrow(QueryException::class);
});

it('frees the active assignment key once an assignment is cancelled', function (): void {
    $line = CrewRequestLine::factory()->create();
    $member = CrewMember::factory()->create();

    CrewAssignment::factory()->forLine($line)->forMember($member)->cancelled()->create();
    $replacement = CrewAssignment::factory()->forLine($line)->forMember($member)->create();

    expect($replacement->exists)->toBeTrue()
        ->and($replacement->active_assignment_key)->toStartWith('line:');
});
