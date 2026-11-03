<?php

declare(strict_types=1);

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\AuditLog\Models\ActionLog;
use App\Domain\Lists\Database\Seeders\ListOfValuesSeeder;
use App\Domain\Lists\Models\ListName;
use App\Domain\Lists\Models\ListValue;
use Illuminate\Support\Facades\Gate;

it('uses canonical list identities and policies', function (): void {
    $list = ListName::factory()->create();
    $value = ListValue::factory()->forList($list)->create();

    expect($list->getMorphClass())->toBe(ListName::class)
        ->and($value->getMorphClass())->toBe(ListValue::class)
        ->and(class_exists('App\\Models\\ListName'))->toBeFalse()
        ->and(class_exists('App\\Models\\ListValue'))->toBeFalse();

    $this->seed(PermissionSeeder::class);
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('list-values.view');
    expect(Gate::forUser($viewer)->allows('view', $list))->toBeTrue();
});

it('canonicalizes persisted list audit identities', function (): void {
    $list = ListName::factory()->create();
    $value = ListValue::factory()->forList($list)->create();
    ActionLog::factory()->create(['auditable_type' => 'App\\Models\\ListName', 'auditable_id' => $list->id]);
    ActionLog::factory()->create(['auditable_type' => 'App\\Models\\ListValue', 'auditable_id' => $value->id]);

    $migration = require app_path('Domain/Lists/Database/Migrations/2026_11_03_000042_canonicalize_list_identities_in_action_logs_table.php');
    $migration->up();

    expect(ActionLog::query()->where('auditable_type', ListName::class)->count())->toBe(1)
        ->and(ActionLog::query()->where('auditable_type', ListValue::class)->count())->toBe(1);
});

it('runs the list seeder through its canonical class', function (): void {
    $this->artisan('db:seed', ['--class' => ListOfValuesSeeder::class, '--force' => true])->assertSuccessful();
    expect(ListName::query()->where('name', 'Activity Type')->exists())->toBeTrue();
});

arch('Lists presentation does not depend on Volt', function (): void {
    expect('App\\Domain\\Lists')->not->toUse('Livewire\\Volt');
});
