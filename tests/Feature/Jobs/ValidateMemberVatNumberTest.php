<?php

use App\Actions\Members\ProcessMemberVatValidation;
use App\Contracts\Tax\VatNumberValidator;
use App\Exceptions\Tax\ViesServiceUnavailableException;
use App\Jobs\ValidateMemberVatNumber;
use App\Models\Member;
use App\ValueObjects\Tax\VatValidationResult;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\ThrottlesExceptions;

it('uses rate limiting and throttles VIES outages', function () {
    $job = new ValidateMemberVatNumber(1);
    $middleware = $job->middleware();

    expect($middleware)->toHaveCount(2)
        ->and($middleware[0])->toBeInstanceOf(RateLimited::class)
        ->and($middleware[1])->toBeInstanceOf(ThrottlesExceptions::class);
});

it('defines exponential backoff for retries', function () {
    $job = new ValidateMemberVatNumber(1);

    expect($job->backoff())->toBe([60, 300, 900]);
});

it('re-throws VIES service unavailability for throttled retries', function () {
    $member = Member::factory()->organisation()->create([
        'tax_number' => 'DE123456789',
    ]);

    $validator = Mockery::mock(VatNumberValidator::class);
    $validator->shouldReceive('validate')
        ->once()
        ->with('DE123456789')
        ->andThrow(new ViesServiceUnavailableException('VIES down'));

    app()->instance(VatNumberValidator::class, $validator);

    expect(fn () => (new ValidateMemberVatNumber($member->id))->handle(app(ProcessMemberVatValidation::class)))
        ->toThrow(ViesServiceUnavailableException::class);
});

it('persists a successful validation result', function () {
    $member = Member::factory()->organisation()->create([
        'tax_number' => 'DE123456789',
        'tax_number_validated' => null,
    ]);

    $validator = Mockery::mock(VatNumberValidator::class);
    $validator->shouldReceive('validate')
        ->once()
        ->with('DE123456789')
        ->andReturn(new VatValidationResult(
            isValid: true,
            source: 'vies',
            traderName: 'Example GmbH',
            traderAddress: 'Berlin',
            countryCode: 'DE',
            vatNumber: '123456789',
        ));

    app()->instance(VatNumberValidator::class, $validator);

    (new ValidateMemberVatNumber($member->id))->handle(app(ProcessMemberVatValidation::class));

    $member->refresh();

    expect($member->tax_number_validated)->toBeTrue()
        ->and($member->tax_number_validated_at)->not->toBeNull()
        ->and($member->tax_number_validation)->toMatchArray([
            'trader_name' => 'Example GmbH',
            'trader_address' => 'Berlin',
            'source' => 'vies',
            'country_code' => 'DE',
            'vat_number' => '123456789',
        ]);
});

it('skips validation when the member no longer has a tax number', function () {
    $member = Member::factory()->organisation()->create([
        'tax_number' => null,
    ]);

    $validator = Mockery::mock(VatNumberValidator::class);
    $validator->shouldNotReceive('validate');
    app()->instance(VatNumberValidator::class, $validator);

    (new ValidateMemberVatNumber($member->id))->handle(app(ProcessMemberVatValidation::class));
});
