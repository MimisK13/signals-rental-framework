<?php

declare(strict_types=1);

use App\Domain\Crew\Actions\Rates\CreateCrewRateCard;
use App\Domain\Crew\Actions\Rates\ResolveCrewRate;
use App\Domain\Crew\Data\Rates\CreateCrewRateCardData;
use App\Domain\Crew\Data\Rates\ResolveCrewRateData;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewRateCard;
use App\Domain\Crew\Models\CrewRole;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Validation\ValidationException;

it('resolves member rates ahead of role defaults and freezes policy settings', function (): void {
    $this->seed(PermissionSeeder::class);
    $user = User::factory()->create(['is_admin' => true]);
    $user->givePermissionTo(['crew.rates.manage', 'crew.rates.view']);
    $this->actingAs($user);
    $member = CrewMember::factory()->create();
    $role = CrewRole::factory()->create();
    $store = Store::factory()->create();
    CrewRateCard::factory()->create(['crew_role_id' => $role->id, 'crew_member_id' => null, 'hourly_rate_minor' => 1000, 'effective_from' => '2026-01-01', 'effective_to' => null]);
    $specific = app(CreateCrewRateCard::class)(new CreateCrewRateCardData('GBP', '2026-01-01', crew_member_id: $member->id, hourly_rate_minor: 0));

    $result = app(ResolveCrewRate::class)(new ResolveCrewRateData($member->id, $role->id, $store->id, '2026-11-02'));

    expect($result->rate_card_id)->toBe($specific->id);
    expect($result->rate_amount_minor)->toBe(0);
    expect($result->allowances_snapshot['_policy'])->toHaveKey('rounding_mode');
});

it('rejects overlapping active cards for exactly the same criteria', function (): void {
    $this->seed(PermissionSeeder::class);
    $user = User::factory()->create(['is_admin' => true]);
    $user->givePermissionTo('crew.rates.manage');
    $this->actingAs($user);
    app(CreateCrewRateCard::class)(new CreateCrewRateCardData('GBP', '2026-01-01', effective_to: '2026-12-31', hourly_rate_minor: 4500));

    expect(function (): void {
        app(CreateCrewRateCard::class)(new CreateCrewRateCardData('GBP', '2026-12-31', hourly_rate_minor: 5000));
    })->toThrow(ValidationException::class);

    $this->assertDatabaseCount('crew_rate_cards', 1);
});

it('permits adjacent effective dates without changing old card values', function (): void {
    $this->seed(PermissionSeeder::class);
    $user = User::factory()->create(['is_admin' => true]);
    $user->givePermissionTo('crew.rates.manage');
    $this->actingAs($user);
    app(CreateCrewRateCard::class)(new CreateCrewRateCardData('GBP', '2026-01-01', effective_to: '2026-06-30', hourly_rate_minor: 4500));

    app(CreateCrewRateCard::class)(new CreateCrewRateCardData('GBP', '2026-07-01', hourly_rate_minor: 5000));

    $this->assertDatabaseHas('crew_rate_cards', ['effective_to' => '2026-06-30 00:00:00', 'hourly_rate_minor' => 4500]);
    $this->assertDatabaseCount('crew_rate_cards', 2);
});

it('rejects an unsupported currency before storing a rate that cannot be formatted', function (): void {
    $this->seed(PermissionSeeder::class);
    $user = User::factory()->create(['is_admin' => true]);
    $user->givePermissionTo('crew.rates.manage');
    $this->actingAs($user);

    expect(function (): void {
        app(CreateCrewRateCard::class)(new CreateCrewRateCardData('ZZZ', '2026-01-01', hourly_rate_minor: 4500));
    })->toThrow(ValidationException::class);

    $this->assertDatabaseCount('crew_rate_cards', 0);
});
