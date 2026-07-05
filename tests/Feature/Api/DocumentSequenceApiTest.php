<?php

use App\Models\DocumentSequence;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create(['is_owner' => true, 'is_active' => true]);
    Sanctum::actingAs($this->user, ['*']);
});

describe('GET /api/v1/document_sequences', function () {
    it('lists document sequences with preview numbers', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);
        $sequence = DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => 'invoice',
            'prefix' => 'INV-',
            'include_year' => true,
            'padding' => 4,
            'next_number' => 12,
        ]);

        $response = $this->getJson('/api/v1/document_sequences')
            ->assertOk()
            ->assertJsonStructure([
                'document_sequences' => [
                    '*' => [
                        'id',
                        'store_id',
                        'document_type',
                        'prefix',
                        'suffix',
                        'include_year',
                        'include_month',
                        'next_number',
                        'padding',
                        'reset_frequency',
                        'next_number_preview',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ]);

        expect($response->json('document_sequences.0.id'))->toBe($sequence->id)
            ->and($response->json('document_sequences.0.next_number_preview'))->toBeString()
            ->and($response->json('document_sequences.0.next_number'))->toBe(12);
    });

    it('forbids callers without document_templates.view', function () {
        $viewer = User::factory()->create(['is_owner' => false, 'is_active' => true]);
        Sanctum::actingAs($viewer, ['*']);

        $this->getJson('/api/v1/document_sequences')->assertForbidden();
    });

    it('scopes sequences to stores the caller can access', function () {
        $storeA = Store::factory()->create(['name' => 'Store A']);
        $storeB = Store::factory()->create(['name' => 'Store B']);

        $sequenceA = DocumentSequence::factory()->create([
            'store_id' => $storeA->id,
            'document_type' => 'invoice',
            'prefix' => 'A-',
        ]);

        $sequenceB = DocumentSequence::factory()->create([
            'store_id' => $storeB->id,
            'document_type' => 'invoice',
            'prefix' => 'B-',
        ]);

        $member = Member::factory()->create();
        $restrictedUser = User::factory()->create(['member_id' => $member->id, 'is_owner' => false, 'is_active' => true]);
        $restrictedUser->givePermissionTo('document_templates.view');
        MemberStore::create(['member_id' => $member->id, 'store_id' => $storeA->id, 'created_at' => now()]);

        Sanctum::actingAs($restrictedUser, ['documents:read']);

        $response = $this->getJson('/api/v1/document_sequences')->assertOk();

        $ids = collect($response->json('document_sequences'))->pluck('id')->all();

        expect($ids)->toContain($sequenceA->id)
            ->and($ids)->not->toContain($sequenceB->id);
    });

    it('allows unrestricted admins to list sequences from every store', function () {
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();

        $sequenceA = DocumentSequence::factory()->create(['store_id' => $storeA->id, 'document_type' => 'quote']);
        $sequenceB = DocumentSequence::factory()->create(['store_id' => $storeB->id, 'document_type' => 'quote']);

        $admin = User::factory()->admin()->create(['is_active' => true]);
        $admin->givePermissionTo('document_templates.view');
        Sanctum::actingAs($admin, ['documents:read']);

        $response = $this->getJson('/api/v1/document_sequences')->assertOk();

        $ids = collect($response->json('document_sequences'))->pluck('id')->all();

        expect($ids)->toContain($sequenceA->id, $sequenceB->id);
    });
});

describe('PUT /api/v1/document_sequences/{documentSequence}', function () {
    it('updates numbering configuration', function () {
        $store = Store::factory()->create();
        $sequence = DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => 'quote',
            'prefix' => 'Q-',
            'padding' => 3,
            'next_number' => 5,
        ]);

        $this->putJson("/api/v1/document_sequences/{$sequence->id}", [
            'prefix' => 'QUO-',
            'padding' => 5,
            'reset_frequency' => 'yearly',
        ])
            ->assertOk()
            ->assertJsonPath('document_sequence.prefix', 'QUO-')
            ->assertJsonPath('document_sequence.padding', 5)
            ->assertJsonPath('document_sequence.reset_frequency', 'yearly')
            ->assertJsonPath('document_sequence.next_number', 5);

        expect($sequence->refresh()->prefix)->toBe('QUO-')
            ->and($sequence->padding)->toBe(5)
            ->and($sequence->reset_frequency)->toBe('yearly');
    });

    it('forbids callers without document_templates.edit', function () {
        $sequence = DocumentSequence::factory()->create();
        $admin = User::factory()->admin()->create(['is_active' => true]);
        $admin->givePermissionTo('document_templates.view');
        Sanctum::actingAs($admin, ['documents:write']);

        $this->putJson("/api/v1/document_sequences/{$sequence->id}", [
            'prefix' => 'X-',
        ])->assertForbidden();
    });

    it('returns not found when updating a sequence outside the caller store scope', function () {
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();

        $sequenceB = DocumentSequence::factory()->create([
            'store_id' => $storeB->id,
            'document_type' => 'invoice',
        ]);

        $member = Member::factory()->create();
        $restrictedUser = User::factory()->create(['member_id' => $member->id, 'is_owner' => false, 'is_active' => true]);
        $restrictedUser->givePermissionTo('document_templates.edit');
        MemberStore::create(['member_id' => $member->id, 'store_id' => $storeA->id, 'created_at' => now()]);

        Sanctum::actingAs($restrictedUser, ['documents:write']);

        $this->putJson("/api/v1/document_sequences/{$sequenceB->id}", [
            'prefix' => 'HACK-',
        ])->assertNotFound();
    });
});
