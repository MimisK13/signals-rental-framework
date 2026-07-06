<?php

use App\Models\Member;
use App\Models\Store;
use App\Models\User;
use App\Schemas\DocumentTaxGroupSchema;
use App\Services\SchemaRegistry;
use Database\Seeders\RoleSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create(['is_owner' => true, 'is_active' => true]);
    Sanctum::actingAs($this->user, ['*']);
});

describe('member tax field-registry exposure', function () {
    it('registers tax zone, buyer type, exempt flag, and VAT validation fields', function () {
        $schema = (new SchemaRegistry)->resolve(Member::class);

        expect($schema)
            ->toHaveKeys([
                'tax_zone_id',
                'buyer_type',
                'tax_exempt',
                'tax_number_validated',
                'tax_number_validated_at',
                'tax_number_validation',
            ])
            ->and($schema['tax_zone_id']->type)->toBe('relation')
            ->and($schema['buyer_type']->type)->toBe('enum')
            ->and($schema['tax_exempt']->type)->toBe('boolean')
            ->and($schema['tax_number_validated']->type)->toBe('boolean')
            ->and($schema['tax_number_validated_at']->type)->toBe('datetime')
            ->and($schema['tax_number_validation']->type)->toBe('json');
    });

    it('exposes member tax fields via the schema API', function () {
        $this->getJson('/api/v1/schema/members')
            ->assertOk()
            ->assertJsonPath('fields.tax_zone_id.type', 'relation')
            ->assertJsonPath('fields.buyer_type.type', 'enum')
            ->assertJsonPath('fields.tax_exempt.type', 'boolean')
            ->assertJsonPath('fields.tax_number_validated.type', 'boolean');
    });
});

describe('store tax field-registry exposure', function () {
    it('registers tax_zone_id on stores', function () {
        $schema = (new SchemaRegistry)->resolve(Store::class);

        expect($schema)
            ->toHaveKey('tax_zone_id')
            ->and($schema['tax_zone_id']->type)->toBe('relation');
    });

    it('exposes store tax_zone_id via the schema API', function () {
        $this->getJson('/api/v1/schema/stores')
            ->assertOk()
            ->assertJsonPath('fields.tax_zone_id.type', 'relation');
    });
});

describe('document tax breakdown field-registry exposure', function () {
    it('registers result_type, legal_text, and monetary breakdown fields', function () {
        $schema = (new SchemaRegistry)->resolve(DocumentTaxGroupSchema::class);

        expect($schema)
            ->toHaveKeys([
                'result_type',
                'legal_text',
                'net_total',
                'tax_total',
                'rate_percentage',
                'tax_rate_name',
            ])
            ->and($schema['result_type']->type)->toBe('enum')
            ->and($schema['legal_text']->type)->toBe('text')
            ->and($schema['net_total']->type)->toBe('integer')
            ->and($schema['tax_total']->type)->toBe('integer')
            ->and($schema['rate_percentage']->type)->toBe('decimal')
            ->and($schema['tax_rate_name']->type)->toBe('string');
    });

    it('exposes document_tax_groups via the schema API', function () {
        $this->getJson('/api/v1/schema/document_tax_groups')
            ->assertOk()
            ->assertJsonFragment(['model' => 'document_tax_groups', 'model_class' => 'DocumentTaxGroupSchema'])
            ->assertJsonPath('fields.result_type.type', 'enum')
            ->assertJsonPath('fields.legal_text.type', 'text')
            ->assertJsonPath('fields.net_total.format', 'currency');
    });
});

describe('schema.read permission gating for document_tax_groups', function () {
    it('allows an admin with schema.read to fetch document_tax_groups', function () {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create(['is_owner' => false, 'is_active' => true]);
        $admin->assignRole('Admin');
        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/v1/schema/document_tax_groups')->assertOk();
    });
});
