<?php

declare(strict_types=1);

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewAvailability;
use App\Domain\Crew\Models\CrewExpense;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewRateCard;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Models\CrewShift;
use App\Domain\Crew\Models\CrewTimesheet;
use App\Domain\Crew\Policies\CrewAssignmentPolicy;
use App\Domain\Crew\Policies\CrewAvailabilityPolicy;
use App\Domain\Crew\Policies\CrewExpensePolicy;
use App\Domain\Crew\Policies\CrewMemberPolicy;
use App\Domain\Crew\Policies\CrewRateCardPolicy;
use App\Domain\Crew\Policies\CrewRequestPolicy;
use App\Domain\Crew\Policies\CrewShiftPolicy;
use App\Domain\Crew\Policies\CrewTimesheetPolicy;
use App\Domain\Crew\Settings\CrewSettings;
use App\Domain\Members\Models\MemberStore;
use App\Domain\Settings\Services\SettingsRegistry;
use App\Domain\Stores\Models\Store;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
});

it('registers the crew settings group from the domain provider', function (): void {
    $registry = app(SettingsRegistry::class);
    $definition = $registry->get('crew');

    expect($definition)->toBeInstanceOf(CrewSettings::class)
        ->and($definition->group())->toBe('crew')
        ->and($registry->defaults('crew'))->toHaveKeys([
            'rounding_mode',
            'daily_ot_multiplier',
            'min_rest_hours',
            'cancellation_pay_hours',
        ])
        ->and($registry->types('crew')['rounding_increment_minutes'])->toBe('integer')
        ->and($registry->defaults('crew')['daily_ot_multiplier'])->toBe('1.50')
        ->and($registry->rules('crew'))->toHaveKey('rounding_mode');
});

it('seeds the crew permission catalogue with valid dependencies', function (): void {
    $permissions = PermissionSeeder::permissions();
    $crew = array_filter($permissions, static fn (array $meta): bool => $meta['group'] === 'Crew');

    expect($crew)->toHaveCount(34)
        ->and($permissions['crew.access']['layer'])->toBe('area')
        ->and($permissions['crew.timesheets.approve']['dependencies'])->toBe(['crew.timesheets.view'])
        ->and($permissions['crew.assignments.confirm']['dependencies'])->toBe(['crew.assignments.create']);

    foreach ($crew as $key => $meta) {
        foreach ($meta['dependencies'] as $dependency) {
            expect($permissions)->toHaveKey($dependency);
        }

        expect($meta['dependencies'])->not->toContain($key);
    }
});

it('registers a policy for every crew model', function (string $model, string $policy): void {
    expect(Gate::getPolicyFor($model))->toBeInstanceOf($policy);
})->with([
    [CrewMember::class, CrewMemberPolicy::class],
    [CrewRateCard::class, CrewRateCardPolicy::class],
    [CrewRequest::class, CrewRequestPolicy::class],
    [CrewAssignment::class, CrewAssignmentPolicy::class],
    [CrewShift::class, CrewShiftPolicy::class],
    [CrewAvailability::class, CrewAvailabilityPolicy::class],
    [CrewTimesheet::class, CrewTimesheetPolicy::class],
    [CrewExpense::class, CrewExpensePolicy::class],
]);

it('authorizes crew abilities from the permission catalogue', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(['crew.view', 'crew.create']);
    $member = CrewMember::factory()->create();

    expect($user->can('viewAny', CrewMember::class))->toBeTrue()
        ->and($user->can('view', $member))->toBeTrue()
        ->and($user->can('create', CrewMember::class))->toBeTrue()
        ->and($user->can('update', $member))->toBeFalse()
        ->and($user->can('viewSensitive', $member))->toBeFalse()
        ->and($user->can('delete', $member))->toBeFalse();
});

it('hides store-scoped crew records outside the user store access', function (): void {
    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();

    $user = User::factory()->create();
    MemberStore::create([
        'member_id' => $user->member_id,
        'store_id' => $storeA->id,
        'created_at' => now(),
    ]);
    $user->givePermissionTo('crew.requests.view');

    $requestA = CrewRequest::factory()->forStore($storeA)->create();
    $requestB = CrewRequest::factory()->forStore($storeB)->create();

    expect($user->can('view', $requestA))->toBeTrue()
        ->and($user->can('view', $requestB))->toBeFalse();
});

it('blocks self-approval of a timesheet without the break-glass lock permission', function (): void {
    $user = User::factory()->create(['is_admin' => true]);
    $user->givePermissionTo('crew.timesheets.approve');

    $member = CrewMember::factory()->for($user)->create();
    $timesheet = CrewTimesheet::factory()
        ->submitted()
        ->for($member, 'crewMember')
        ->create();

    expect($user->can('approve', $timesheet))->toBeFalse();

    $user->givePermissionTo('crew.timesheets.lock');

    expect($user->can('approve', $timesheet))->toBeTrue();
});

it('lets crew members view their own availability without the crew permission', function (): void {
    $user = User::factory()->create();
    $member = CrewMember::factory()->for($user)->create();
    $availability = CrewAvailability::factory()->for($member, 'crewMember')->create();

    expect($user->can('view', $availability))->toBeTrue()
        ->and($user->can('create', CrewAvailability::class))->toBeFalse();
});
