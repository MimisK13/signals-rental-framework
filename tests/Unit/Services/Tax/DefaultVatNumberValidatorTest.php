<?php

use App\Exceptions\Tax\ViesServiceUnavailableException;
use App\Services\Tax\DefaultVatNumberValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['tax.vies_rest_url' => 'https://vies.test/check-vat-number']);
});

it('validates a UK VAT number with a passing checksum', function (string $taxNumber) {
    $result = app(DefaultVatNumberValidator::class)->validate($taxNumber);

    expect($result->isValid)->toBeTrue()
        ->and($result->source)->toBe('uk_checksum')
        ->and($result->countryCode)->toBe('GB');
})->with([
    'standard' => 'GB100000018',
    'alternate' => 'GB382500035',
]);

it('rejects a UK VAT number with a failing checksum', function (string $taxNumber) {
    $result = app(DefaultVatNumberValidator::class)->validate($taxNumber);

    expect($result->isValid)->toBeFalse()
        ->and($result->source)->toBe('uk_checksum');
})->with([
    'wrong digits' => 'GB123456789',
    'another invalid' => 'GB999999999',
]);

it('accepts a valid EU VAT number from VIES', function () {
    Http::fake([
        'vies.test/*' => Http::response([
            'valid' => true,
            'name' => 'Example GmbH',
            'address' => 'Berlin, Germany',
        ]),
    ]);

    $result = app(DefaultVatNumberValidator::class)->validate('DE123456789');

    expect($result->isValid)->toBeTrue()
        ->and($result->source)->toBe('vies')
        ->and($result->traderName)->toBe('Example GmbH')
        ->and($result->traderAddress)->toBe('Berlin, Germany')
        ->and($result->countryCode)->toBe('DE')
        ->and($result->vatNumber)->toBe('123456789');
});

it('rejects an invalid EU VAT number from VIES', function () {
    Http::fake([
        'vies.test/*' => Http::response([
            'valid' => false,
        ]),
    ]);

    $result = app(DefaultVatNumberValidator::class)->validate('DE999999999');

    expect($result->isValid)->toBeFalse()
        ->and($result->source)->toBe('vies');
});

it('throws when VIES is unreachable', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    expect(fn () => app(DefaultVatNumberValidator::class)->validate('DE123456789'))
        ->toThrow(ViesServiceUnavailableException::class);
});

it('throws when VIES returns a server error', function () {
    Http::fake([
        'vies.test/*' => Http::response('Service unavailable', 503),
    ]);

    expect(fn () => app(DefaultVatNumberValidator::class)->validate('DE123456789'))
        ->toThrow(ViesServiceUnavailableException::class);
});

it('throws when VIES rate limits the request', function () {
    Http::fake([
        'vies.test/*' => Http::response('Too many requests', 429),
    ]);

    expect(fn () => app(DefaultVatNumberValidator::class)->validate('DE123456789'))
        ->toThrow(ViesServiceUnavailableException::class);
});

it('rejects malformed tax numbers without calling VIES', function () {
    Http::fake();

    $result = app(DefaultVatNumberValidator::class)->validate('X');

    expect($result->isValid)->toBeFalse()
        ->and($result->source)->toBe('format');

    Http::assertNothingSent();
});
