<?php

use App\Actions\Members\CreateMember;
use App\Data\Members\CreateMemberData;
use App\Enums\MembershipType;
use App\Models\DocumentSequence;
use App\Models\Member;
use App\Models\Store;
use App\Models\User;
use App\Services\Documents\DocumentSequenceDefaults;
use App\Services\Members\MemberNumberGenerator;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

describe('MemberNumberGenerator', function () {
    it('formats numbers from document sequences with prefix and padding', function () {
        $store = Store::factory()->create(['is_default' => true, 'timezone' => 'UTC']);
        DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => MemberNumberGenerator::DOCUMENT_TYPE,
            'prefix' => 'MEM-',
            'include_year' => true,
            'padding' => 4,
            'next_number' => 7,
            'is_entity_number' => true,
        ]);

        $at = CarbonImmutable::parse('2026-03-01 00:00:00', 'UTC');
        $generator = app(MemberNumberGenerator::class);

        expect($generator->next($at))->toBe('MEM-2026-0007')
            ->and($generator->next($at))->toBe('MEM-2026-0008');
    });

    it('creates a default sequence on first use with entity flag and MEM- prefix', function () {
        $store = Store::factory()->create(['is_default' => true, 'timezone' => 'UTC']);
        $generator = app(MemberNumberGenerator::class);

        expect($generator->nextOrCreate())
            ->toBe('MEM-'.now()->format('Y').'-0001');

        $sequence = DocumentSequence::query()
            ->where('store_id', $store->id)
            ->where('document_type', MemberNumberGenerator::DOCUMENT_TYPE)
            ->first();

        expect($sequence)->not->toBeNull()
            ->and($sequence->prefix)->toBe('MEM-')
            ->and($sequence->is_entity_number)->toBeTrue();
    });

    it('increments sequentially under repeated allocation', function () {
        $store = Store::factory()->create(['is_default' => true, 'timezone' => 'UTC']);
        DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => MemberNumberGenerator::DOCUMENT_TYPE,
            'prefix' => 'MEM-',
            'include_year' => false,
            'padding' => 3,
            'next_number' => 1,
            'is_entity_number' => true,
        ]);

        $generator = app(MemberNumberGenerator::class);

        expect($generator->next())->toBe('MEM-001')
            ->and($generator->next())->toBe('MEM-002')
            ->and($generator->next())->toBe('MEM-003');
    });

    it('uses the default store sequence row for global member numbers', function () {
        $defaultStore = Store::factory()->create(['is_default' => true, 'timezone' => 'UTC']);
        Store::factory()->create(['is_default' => false, 'timezone' => 'UTC']);

        DocumentSequence::factory()->create([
            'store_id' => $defaultStore->id,
            'document_type' => MemberNumberGenerator::DOCUMENT_TYPE,
            'prefix' => 'MEM-',
            'include_year' => false,
            'padding' => 2,
            'next_number' => 5,
            'is_entity_number' => true,
        ]);

        expect(app(MemberNumberGenerator::class)->next())->toBe('MEM-05');
    });
});

describe('CreateMember numbering', function () {
    beforeEach(function () {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->actingAs(User::factory()->owner()->create());
        Store::factory()->create(['is_default' => true, 'timezone' => 'UTC']);
    });

    it('assigns a MEM- number to new members', function () {
        $result = (new CreateMember)(CreateMemberData::from([
            'name' => 'Numbered Corp',
            'membership_type' => MembershipType::Organisation->value,
        ]));

        $member = Member::query()->findOrFail($result->id);

        expect($member->number)->toStartWith('MEM-');
    });

    it('does not backfill numbers onto existing members', function () {
        $existing = Member::factory()->organisation()->create(['number' => null]);

        (new CreateMember)(CreateMemberData::from([
            'name' => 'Another Corp',
            'membership_type' => MembershipType::Organisation->value,
        ]));

        expect($existing->refresh()->number)->toBeNull();
    });
});

it('provisions member sequences with is_entity_number via DocumentSequenceDefaults', function () {
    $store = Store::factory()->create(['timezone' => 'UTC']);

    $sequence = DocumentSequenceDefaults::ensureExists($store->id, MemberNumberGenerator::DOCUMENT_TYPE);

    expect($sequence->prefix)->toBe('MEM-')
        ->and($sequence->is_entity_number)->toBeTrue();
});
