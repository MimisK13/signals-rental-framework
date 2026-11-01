<?php

declare(strict_types=1);

use App\Domain\Activities\Models\Activity;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Currency\Models\Currency;
use App\Domain\Currency\Models\ExchangeRate;
use App\Domain\Currency\Services\CurrencyService;
use App\Domain\Stocktakes\Models\Stocktake;
use App\Models\ActionLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

it('resolves historical currency audit references without changing stored morph keys', function (string $legacy, string $canonical): void {
    $model = $canonical::factory()->create();
    $log = ActionLog::factory()->create([
        'auditable_type' => $legacy,
        'auditable_id' => $model->getKey(),
    ]);

    expect($log->fresh()->auditable)->toBeInstanceOf($canonical);
    expect($model->getMorphClass())->toBe($legacy);
    expect($legacy::query()->findOrFail($model->getKey()))->toBeInstanceOf($canonical);
})->with([
    'currency' => ['App\\Models\\Currency', Currency::class],
    'exchange rate' => ['App\\Models\\ExchangeRate', ExchangeRate::class],
]);

it('resolves legacy currency service consumers through the canonical container binding', function (): void {
    $service = new CurrencyService;
    $this->app->instance(CurrencyService::class, $service);

    expect(app('App\\Services\\CurrencyService'))->toBe($service);
});

it('preserves the short currency and exchange rate seed commands', function (): void {
    $this->artisan('db:seed', ['--class' => 'CurrencySeeder', '--force' => true])->assertSuccessful();
    $this->artisan('db:seed', ['--class' => 'ExchangeRateSeeder', '--force' => true])->assertSuccessful();

    expect(Currency::query()->where('code', 'GBP')->firstOrFail()->is_enabled)->toBeTrue();
    expect(app(CurrencyService::class)->getRate('GBP', 'USD', Carbon::parse('2026-02-01T00:00:00Z')))->toBe('1.27000000');
});

it('discovers the exchange rate policy for canonical models and legacy class names', function (): void {
    $owner = User::factory()->owner()->create();
    $viewer = User::factory()->create();
    $rate = ExchangeRate::factory()->create();

    expect(Gate::forUser($owner)->allows('update', $rate))->toBeTrue();
    expect(Gate::forUser($viewer)->allows('update', $rate))->toBeFalse();
    expect(Gate::forUser($owner)->allows('viewAny', 'App\\Models\\ExchangeRate'))->toBeTrue();
});

it('keeps currency factories compatible with the other extracted domains', function (): void {
    $rate = ExchangeRate::factory()->pair('EUR', 'USD', '1.25000000')->create();

    expect($rate->rate)->toBe('1.25000000');
    expect(Currency::query()->whereIn('code', ['EUR', 'USD'])->count())->toBe(2);
    $this->assertModelExists(Activity::factory()->create());
    $this->assertModelExists(CrewMember::factory()->create());
    $this->assertModelExists(Stocktake::factory()->create());
});

arch('Currency presentation does not depend on Volt', function (): void {
    expect('App\Domain\Currency')->not->toUse('Livewire\Volt');
});
