<?php

declare(strict_types=1);

use App\Domain\Administration\Models\User;
use App\Domain\AuditLog\Models\ActionLog;
use App\Domain\Currency\Database\Seeders\CurrencySeeder;
use App\Domain\Currency\Database\Seeders\ExchangeRateSeeder;
use App\Domain\Currency\Models\Currency;
use App\Domain\Currency\Models\ExchangeRate;
use App\Domain\Currency\Services\CurrencyService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

it('uses canonical currency identities and policy discovery', function (): void {
    $currency = Currency::factory()->create();
    $rate = ExchangeRate::factory()->create();
    $owner = User::factory()->owner()->create();

    expect($currency->getMorphClass())->toBe(Currency::class)
        ->and($rate->getMorphClass())->toBe(ExchangeRate::class)
        ->and(class_exists('App\\Models\\Currency'))->toBeFalse()
        ->and(class_exists('App\\Models\\ExchangeRate'))->toBeFalse()
        ->and(app()->bound('App\\Services\\CurrencyService'))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('update', $rate))->toBeTrue();
});

it('canonicalizes persisted currency audit identities', function (): void {
    $currency = Currency::factory()->create();
    $rate = ExchangeRate::factory()->create();
    ActionLog::factory()->create(['auditable_type' => 'App\\Models\\Currency', 'auditable_id' => $currency->id]);
    ActionLog::factory()->create(['auditable_type' => 'App\\Models\\ExchangeRate', 'auditable_id' => $rate->id]);

    $migration = require app_path('Domain/Currency/Database/Migrations/2026_11_03_000041_canonicalize_currency_identities_in_action_logs_table.php');
    $migration->up();

    expect(ActionLog::query()->where('auditable_type', Currency::class)->count())->toBe(1)
        ->and(ActionLog::query()->where('auditable_type', ExchangeRate::class)->count())->toBe(1);
});

it('runs currency seeders through canonical classes', function (): void {
    $this->artisan('db:seed', ['--class' => CurrencySeeder::class, '--force' => true])->assertSuccessful();
    $this->artisan('db:seed', ['--class' => ExchangeRateSeeder::class, '--force' => true])->assertSuccessful();

    expect(app(CurrencyService::class)->getRate('GBP', 'USD', Carbon::parse('2026-02-01T00:00:00Z')))->toBe('1.27000000');
});

arch('Currency presentation does not depend on Volt', function (): void {
    expect('App\\Domain\\Currency')->not->toUse('Livewire\\Volt');
});
