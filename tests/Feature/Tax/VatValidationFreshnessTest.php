<?php

use App\Jobs\ValidateMemberVatNumber;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use App\Services\Tax\TaxResolutionContext;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-04T12:00:00Z'));
    settings()->set('finance.vat_validation_validity_days', 30);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function vatFreshnessContext(int $daysAgo): TaxResolutionContext
{
    $member = Member::factory()->organisation()->create([
        'tax_number' => 'DE123456789',
        'tax_number_validated' => true,
        'tax_number_validated_at' => CarbonImmutable::now()->subDays($daysAgo),
    ]);
    $store = Store::factory()->create();
    $opportunity = Opportunity::factory()->create([
        'member_id' => $member->id,
        'store_id' => $store->id,
    ]);

    return TaxResolutionContext::fromOpportunity($opportunity);
}

describe('VAT validation freshness in tax resolution', function () {
    it('treats a validation from 29 days ago as still valid', function () {
        expect(vatFreshnessContext(29)->hasTaxId)->toBeTrue();
    });

    it('treats a validation from exactly 30 days ago as expired', function () {
        expect(vatFreshnessContext(30)->hasTaxId)->toBeFalse();
    });

    it('treats a validation from 31 days ago as expired', function () {
        expect(vatFreshnessContext(31)->hasTaxId)->toBeFalse();
    });

    it('never expires when finance.vat_validation_validity_days is 0', function () {
        settings()->set('finance.vat_validation_validity_days', 0);

        expect(vatFreshnessContext(365)->hasTaxId)->toBeTrue();
    });

    it('never expires when finance.vat_validation_validity_days is null', function () {
        settings()->set('finance.vat_validation_validity_days', null);

        expect(vatFreshnessContext(365)->hasTaxId)->toBeTrue();
    });

    it('respects a shorter configured validity window', function () {
        settings()->set('finance.vat_validation_validity_days', 7);

        expect(vatFreshnessContext(6)->hasTaxId)->toBeTrue()
            ->and(vatFreshnessContext(7)->hasTaxId)->toBeFalse();
    });
});

describe('members:revalidate-expired-vat command', function () {
    it('queues revalidation only for members whose validation has expired', function () {
        Queue::fake();

        $expired = Member::factory()->organisation()->create([
            'tax_number' => 'DE111111111',
            'tax_number_validated' => true,
            'tax_number_validated_at' => CarbonImmutable::now()->subDays(31),
        ]);
        $stillValid = Member::factory()->organisation()->create([
            'tax_number' => 'DE222222222',
            'tax_number_validated' => true,
            'tax_number_validated_at' => CarbonImmutable::now()->subDays(29),
        ]);
        Member::factory()->organisation()->create([
            'tax_number' => 'DE333333333',
            'tax_number_validated' => false,
            'tax_number_validated_at' => CarbonImmutable::now()->subDays(60),
        ]);

        $this->artisan('members:revalidate-expired-vat')
            ->assertSuccessful()
            ->expectsOutputToContain('Queued VAT revalidation for 1 member(s)');

        Queue::assertPushed(ValidateMemberVatNumber::class, fn (ValidateMemberVatNumber $job): bool => $job->memberId === $expired->id);
        Queue::assertPushedTimes(ValidateMemberVatNumber::class, 1);
        Queue::assertNotPushed(ValidateMemberVatNumber::class, fn (ValidateMemberVatNumber $job): bool => $job->memberId === $stillValid->id);
    });

    it('does nothing when validations never expire', function () {
        Queue::fake();
        settings()->set('finance.vat_validation_validity_days', 0);

        Member::factory()->organisation()->create([
            'tax_number' => 'DE444444444',
            'tax_number_validated' => true,
            'tax_number_validated_at' => CarbonImmutable::now()->subDays(365),
        ]);

        $this->artisan('members:revalidate-expired-vat')
            ->assertSuccessful()
            ->expectsOutputToContain('never expires');

        Queue::assertNothingPushed();
    });

    it('reports when no expired members are found', function () {
        Queue::fake();

        Member::factory()->organisation()->create([
            'tax_number' => 'DE555555555',
            'tax_number_validated' => true,
            'tax_number_validated_at' => CarbonImmutable::now()->subDays(10),
        ]);

        $this->artisan('members:revalidate-expired-vat')
            ->assertSuccessful()
            ->expectsOutputToContain('No members with expired VAT validation found.');

        Queue::assertNothingPushed();
    });
});
