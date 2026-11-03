<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Members\Models\Member;
use App\Domain\Rates\Models\RateDefinition;
use App\Domain\Stores\Models\Store;
use App\Domain\System\Presentation\Livewire\Admin\Seeders;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('renders the seeders page', function () {
    $this->get(route('admin.settings.seeders'))
        ->assertOk()
        ->assertSee('Database Seeders');
});

it('shows seeder status as seeded when data exists', function () {
    Livewire::test(Seeders::class)
        ->assertSee('PermissionSeeder')
        ->assertSee('RoleSeeder')
        ->assertSee('StoreSeeder')
        ->assertSee('DemoDataSeeder');
});

it('references only existing allowlisted seeder classes', function () {
    $seeders = Livewire::test(Seeders::class)->get('seeders');

    foreach ($seeders as $seeder) {
        expect(class_exists($seeder['class']))->toBeTrue($seeder['class']);
    }
});

it('detects permissions as seeded', function () {
    $component = Livewire::test(Seeders::class);

    $seeders = $component->get('seeders');
    expect($seeders['permissions']['seeded'])->toBeTrue();
    expect($seeders['roles']['seeded'])->toBeTrue();
});

it('detects stores as not seeded when no stores exist', function () {
    $component = Livewire::test(Seeders::class);

    $seeders = $component->get('seeders');
    expect($seeders['stores']['seeded'])->toBeFalse();
    expect($seeders['demo']['seeded'])->toBeFalse();
});

it('detects demo data as seeded when tagged stores, members, and invoices exist', function () {
    Store::factory()->create(['tag_list' => ['demo-data'], 'is_default' => false]);
    Member::factory()->create(['tag_list' => ['demo-data']]);
    Invoice::factory()->create(['tag_list' => ['demo-data']]);

    $seeders = Livewire::test(Seeders::class)->get('seeders');

    expect($seeders['demo']['seeded'])->toBeTrue();
});

it('describes the phase-4 demo substrate on the seeders page', function () {
    Livewire::test(Seeders::class)
        ->assertSee('Phase-4 UAT substrate')
        ->assertSee('Quote-to-cash')
        ->assertSee('virtual stock intake');
});

it('runs an individual seeder', function () {
    expect(Store::query()->exists())->toBeFalse();

    Livewire::test(Seeders::class)
        ->call('seed', 'stores')
        ->assertDispatched('seeder-completed');

    expect(Store::query()->exists())->toBeTrue();
    expect(Store::query()->where('name', 'Main Warehouse')->exists())->toBeTrue();
});

it('runs all default seeders', function () {
    // Clear permissions and roles to verify re-seeding works
    Permission::query()->delete();
    Role::query()->delete();

    Livewire::test(Seeders::class)
        ->call('seedAll')
        ->assertHasNoErrors()
        ->assertDispatched('seeder-completed');

    expect(Permission::query()->exists())->toBeTrue();
    expect(Role::query()->where('name', 'Admin')->exists())->toBeTrue();
});

it('hides run button for already seeded seeders', function () {
    Livewire::test(Seeders::class)
        ->assertDontSee('Run Default Seeders');
});

it('shows run button for unseeded seeders', function () {
    Permission::query()->delete();
    Role::query()->delete();

    Livewire::test(Seeders::class)
        ->assertSee('Run Default Seeders');
});

it('shows error for invalid seeder key', function () {
    Livewire::test(Seeders::class)
        ->call('seed', 'nonexistent')
        ->assertHasErrors(['seeder']);
});

it('lists and runs the rate definitions preset seeder', function () {
    $component = Livewire::test(Seeders::class);

    $seeders = $component->get('seeders');
    expect($seeders)->toHaveKey('rate_definitions');
    expect($seeders['rate_definitions']['seeded'])->toBeFalse();

    $component->call('seed', 'rate_definitions')
        ->assertDispatched('seeder-completed');

    expect(RateDefinition::where('is_preset', true)->count())->toBe(11);
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.seeders'))
        ->assertForbidden();
});
