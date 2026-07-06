<?php

use App\Models\User;
use App\Services\SchemaRegistry;
use Database\Seeders\RoleSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create(['is_owner' => true, 'is_active' => true]);
    Sanctum::actingAs($this->user, ['*']);
});

describe('GET /api/v1/document_types', function () {
    it('lists the registered opportunity document type', function () {
        $this->getJson('/api/v1/document_types')
            ->assertOk()
            ->assertJsonStructure(['document_types', 'meta'])
            ->assertJsonFragment([
                'type' => 'opportunity',
                'label' => 'Opportunity',
            ]);
    });

    it('forbids callers without documents.view', function () {
        $this->seed(RoleSeeder::class);

        $viewer = User::factory()->create(['is_owner' => false, 'is_active' => true]);
        Sanctum::actingAs($viewer, ['*']);

        $this->getJson('/api/v1/document_types')->assertForbidden();
    });
});

describe('GET /api/v1/document_types/{type}/fields', function () {
    it('returns grouped resolver fields for opportunities', function () {
        $response = $this->getJson('/api/v1/document_types/opportunity/fields')
            ->assertOk()
            ->assertJsonStructure([
                'document_type',
                'groups' => [
                    '*' => [
                        'name',
                        'fields' => [
                            '*' => ['name', 'type', 'label', 'group', 'source'],
                        ],
                    ],
                ],
            ]);

        $groups = collect($response->json('groups'))->pluck('name')->all();

        expect($groups)->toContain('Opportunity', 'Customer', 'Line Items', 'Tax Summary');

        $fieldNames = collect($response->json('groups'))
            ->flatMap(fn (array $group): array => collect($group['fields'])->pluck('name')->all())
            ->all();

        expect($fieldNames)
            ->toContain('opportunity.charge_total', 'member.name', 'items', 'tax_summary.groups', 'tax_summary.group.legal_text');
    });

    it('marks document resolver fields with source=document', function () {
        $fields = collect($this->getJson('/api/v1/document_types/opportunity/fields')->json('groups'))
            ->flatMap(fn (array $group): array => $group['fields'])
            ->all();

        expect($fields)->not->toBeEmpty();

        foreach ($fields as $field) {
            expect($field['source'])->toBe('document');
        }
    });

    it('returns 404 for unknown document types', function () {
        $this->getJson('/api/v1/document_types/unknown/fields')
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'Unknown document type: unknown']);
    });

    it('forbids callers without schema.read', function () {
        $this->seed(RoleSeeder::class);

        $viewer = User::factory()->create(['is_owner' => false, 'is_active' => true]);
        Sanctum::actingAs($viewer, ['*']);

        $this->getJson('/api/v1/document_types/opportunity/fields')->assertForbidden();
    });
});

describe('schema registry delegation', function () {
    it('exposes document resolver fields through SchemaRegistry', function () {
        $definitions = app(SchemaRegistry::class)->resolveDocumentType('opportunity');

        expect($definitions)->toHaveKey('opportunity.subject')
            ->and($definitions['opportunity.subject']->source)->toBe('document');
    });
});
