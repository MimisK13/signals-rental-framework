<?php

use App\Models\DocumentSequence;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
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

        $response = $this->getJson('/api/v1/document_sequences?q[store_id_eq]='.$store->id)
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
        $this->seed(RoleSeeder::class);

        $viewer = User::factory()->create(['is_owner' => false, 'is_active' => true]);
        Sanctum::actingAs($viewer, ['*']);

        $this->getJson('/api/v1/document_sequences')->assertForbidden();
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
        $this->seed(RoleSeeder::class);

        $sequence = DocumentSequence::factory()->create();
        $viewer = User::factory()->create(['is_owner' => false, 'is_active' => true]);
        Sanctum::actingAs($viewer, ['*']);

        $this->putJson("/api/v1/document_sequences/{$sequence->id}", [
            'prefix' => 'X-',
        ])->assertForbidden();
    });
});
