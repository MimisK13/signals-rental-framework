<?php

declare(strict_types=1);

use App\Domain\Activities\Models\Activity;
use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\AuditLog\Models\ActionLog;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Currency\Models\Currency;
use App\Domain\Lists\Models\ListName;
use App\Domain\Lists\Models\ListValue;
use App\Domain\Stock\Stocktakes\Models\Stocktake;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;

it('resolves historical list audit references without changing stored morph keys', function (string $legacy, string $canonical): void {
    $model = $canonical::factory()->create();
    $log = ActionLog::factory()->create([
        'action' => 'list.updated',
        'auditable_type' => $legacy,
        'auditable_id' => $model->getKey(),
    ]);

    expect($log->fresh()->auditable)->toBeInstanceOf($canonical);
    expect($model->getMorphClass())->toBe($legacy);
    expect(Relation::getMorphedModel($legacy))->toBe($canonical);
    expect($legacy::query()->findOrFail($model->getKey()))->toBeInstanceOf($canonical);
})->with([
    'list name' => ['App\\Models\\ListName', ListName::class],
    'list value' => ['App\\Models\\ListValue', ListValue::class],
]);

it('preserves the short list seeder command', function (): void {
    $this->artisan('db:seed', ['--class' => 'ListOfValuesSeeder', '--force' => true])->assertSuccessful();

    expect(ListName::query()->where('name', 'Activity Type')->exists())->toBeTrue();
    expect(ListValue::query()->whereHas('listName', fn ($query) => $query->where('name', 'Activity Type'))->exists())->toBeTrue();
});

it('discovers the list name policy for canonical models and legacy class names', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $owner = User::factory()->owner()->create();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('list-values.view');
    $listName = ListName::factory()->create();

    expect(Gate::forUser($owner)->allows('update', $listName))->toBeTrue();
    expect(Gate::forUser($viewer)->allows('view', $listName))->toBeTrue();
    expect(Gate::forUser($viewer)->allows('update', $listName))->toBeFalse();
    expect(Gate::forUser($owner)->allows('viewAny', 'App\\Models\\ListName'))->toBeTrue();
});

it('keeps list factories compatible with the other extracted domains', function (): void {
    $listName = ListName::factory()->create();
    $value = ListValue::factory()->forList($listName)->create();

    expect($value->listName->is($listName))->toBeTrue();
    $this->assertModelExists(Activity::factory()->create());
    $this->assertModelExists(CrewMember::factory()->create());
    $this->assertModelExists(Currency::factory()->create());
    $this->assertModelExists(Stocktake::factory()->create());
});

it('serves the native list management route with existing records', function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $user = User::factory()->owner()->create();
    ListName::factory()->create(['name' => 'Native presentation list']);

    $this->actingAs($user)->withoutVite()->get(route('admin.settings.list-names'))
        ->assertOk()
        ->assertSee('Native presentation list');
});

arch('Lists presentation does not depend on Volt', function (): void {
    expect('App\Domain\Lists')->not->toUse('Livewire\Volt');
});
