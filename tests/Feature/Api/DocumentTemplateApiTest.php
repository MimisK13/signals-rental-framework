<?php

use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create(['is_owner' => true, 'is_active' => true]);
    $this->user->givePermissionTo(['document_templates.view', 'document_templates.edit']);
    Sanctum::actingAs($this->user, ['documents:read', 'documents:write']);
});

function seedTemplatesForStore(Store $store): void
{
    app(DocumentTemplateSeeder::class)->seedForStore($store);
}

describe('GET /api/v1/document_templates', function () {
    it('lists templates with ransack filters', function () {
        $store = Store::factory()->create();
        seedTemplatesForStore($store);

        $response = $this->getJson('/api/v1/document_templates?q[document_type_eq]=quote&q[is_active_true]=1')
            ->assertOk()
            ->assertJsonStructure([
                'document_templates' => [
                    '*' => [
                        'id',
                        'store_id',
                        'name',
                        'slug',
                        'document_type',
                        'is_default',
                        'is_system',
                        'is_active',
                        'version',
                    ],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ]);

        expect(collect($response->json('document_templates'))->every(
            fn (array $row): bool => $row['document_type'] === 'quote' && $row['is_active'] === true,
        ))->toBeTrue();
    });

    it('scopes templates to stores the caller can access', function () {
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();
        seedTemplatesForStore($storeA);
        seedTemplatesForStore($storeB);

        $member = Member::factory()->create();
        $restrictedUser = User::factory()->create(['member_id' => $member->id, 'is_owner' => false, 'is_active' => true]);
        $restrictedUser->givePermissionTo(['document_templates.view', 'document_templates.edit']);
        MemberStore::create(['member_id' => $member->id, 'store_id' => $storeA->id, 'created_at' => now()]);
        Sanctum::actingAs($restrictedUser, ['documents:read', 'documents:write']);

        $ids = collect($this->getJson('/api/v1/document_templates')->json('document_templates'))
            ->pluck('store_id')
            ->unique()
            ->all();

        expect($ids)->toBe([$storeA->id]);
    });
});

describe('document template CRUD and lifecycle', function () {
    it('creates, shows, updates with version snapshots, and deletes custom templates', function () {
        $store = Store::factory()->create();

        $createResponse = $this->postJson('/api/v1/document_templates', [
            'store_id' => $store->id,
            'name' => 'Custom Quote',
            'slug' => 'custom-quote',
            'document_type' => 'quote',
            'body_html' => '<p>Version 1 body</p>',
        ])->assertCreated();

        $templateId = $createResponse->json('document_template.id');

        $this->getJson("/api/v1/document_templates/{$templateId}")
            ->assertOk()
            ->assertJsonPath('document_template.name', 'Custom Quote')
            ->assertJsonPath('document_template.versions', []);

        $this->putJson("/api/v1/document_templates/{$templateId}", [
            'body_html' => '<p>Version 2 body</p>',
            'change_note' => 'Updated body',
        ])
            ->assertOk()
            ->assertJsonPath('document_template.version', 2)
            ->assertJsonPath('document_template.body_html', '<p>Version 2 body</p>');

        expect(DocumentTemplateVersion::query()->where('document_template_id', $templateId)->count())->toBe(1)
            ->and(DocumentTemplateVersion::query()->where('document_template_id', $templateId)->value('version'))->toBe(1);

        $this->deleteJson("/api/v1/document_templates/{$templateId}")
            ->assertNoContent();

        expect(DocumentTemplate::withTrashed()->find($templateId)?->trashed())->toBeTrue();
    });

    it('sets default template and clears sibling defaults in one transaction', function () {
        $store = Store::factory()->create();
        seedTemplatesForStore($store);

        $systemQuote = DocumentTemplate::query()
            ->where('store_id', $store->id)
            ->where('document_type', 'quote')
            ->where('is_system', true)
            ->firstOrFail();

        $custom = DocumentTemplate::factory()->for($store)->create([
            'document_type' => 'quote',
            'slug' => 'alt-quote',
            'is_default' => false,
            'is_system' => false,
        ]);

        $this->postJson("/api/v1/document_templates/{$custom->id}/set_default")
            ->assertOk()
            ->assertJsonPath('document_template.is_default', true);

        expect($systemQuote->refresh()->is_default)->toBeFalse()
            ->and($custom->refresh()->is_default)->toBeTrue();
    });

    it('duplicates a template as a non-system copy', function () {
        $store = Store::factory()->create();
        seedTemplatesForStore($store);

        $source = DocumentTemplate::query()
            ->where('store_id', $store->id)
            ->where('document_type', 'invoice')
            ->firstOrFail();

        $response = $this->postJson("/api/v1/document_templates/{$source->id}/duplicate")
            ->assertCreated();

        expect($response->json('document_template.name'))->toBe('Copy of '.$source->name)
            ->and($response->json('document_template.is_system'))->toBeFalse()
            ->and($response->json('document_template.is_default'))->toBeFalse()
            ->and($response->json('document_template.slug'))->not->toBe($source->slug);
    });

    it('restores a system template to shipped default content', function () {
        $store = Store::factory()->create();
        seedTemplatesForStore($store);

        $template = DocumentTemplate::query()
            ->where('store_id', $store->id)
            ->where('document_type', 'quote')
            ->where('is_system', true)
            ->firstOrFail();

        $template->update([
            'body_html' => '<p>Broken custom body</p>',
            'version' => 2,
        ]);

        $this->postJson("/api/v1/document_templates/{$template->id}/restore_default")
            ->assertOk();

        $template->refresh();

        expect($template->body_html)->toContain('count($items)')
            ->and($template->version)->toBe(3);
    });

    it('restores content from a historical version snapshot', function () {
        $store = Store::factory()->create();

        $create = $this->postJson('/api/v1/document_templates', [
            'store_id' => $store->id,
            'name' => 'Versioned',
            'slug' => 'versioned-template',
            'document_type' => 'quote',
            'body_html' => '<p>Original</p>',
        ])->assertCreated();

        $templateId = $create->json('document_template.id');

        $this->putJson("/api/v1/document_templates/{$templateId}", [
            'body_html' => '<p>Changed</p>',
        ])->assertOk();

        $version = DocumentTemplateVersion::query()
            ->where('document_template_id', $templateId)
            ->where('version', 1)
            ->firstOrFail();

        $this->postJson("/api/v1/document_templates/{$templateId}/versions/{$version->id}/restore")
            ->assertOk()
            ->assertJsonPath('document_template.body_html', '<p>Original</p>')
            ->assertJsonPath('document_template.version', 3);
    });

    it('returns 422 when deleting a system template', function () {
        $store = Store::factory()->create();
        seedTemplatesForStore($store);

        $template = DocumentTemplate::query()
            ->where('store_id', $store->id)
            ->where('is_system', true)
            ->firstOrFail();

        $this->deleteJson("/api/v1/document_templates/{$template->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['document_template']);
    });
});

describe('document template store-scope IDOR guards', function () {
    it('rejects cross-store read, update, set_default, and duplicate attempts', function () {
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();
        seedTemplatesForStore($storeB);

        $foreignTemplate = DocumentTemplate::query()
            ->where('store_id', $storeB->id)
            ->firstOrFail();

        $member = Member::factory()->create();
        $restrictedUser = User::factory()->create(['member_id' => $member->id, 'is_owner' => false, 'is_active' => true]);
        $restrictedUser->givePermissionTo(['document_templates.view', 'document_templates.edit']);
        MemberStore::create(['member_id' => $member->id, 'store_id' => $storeA->id, 'created_at' => now()]);
        Sanctum::actingAs($restrictedUser, ['documents:read', 'documents:write']);

        $this->getJson("/api/v1/document_templates/{$foreignTemplate->id}")->assertNotFound();

        $this->putJson("/api/v1/document_templates/{$foreignTemplate->id}", [
            'name' => 'Hacked',
        ])->assertNotFound();

        $this->postJson("/api/v1/document_templates/{$foreignTemplate->id}/set_default")
            ->assertNotFound();

        $this->postJson("/api/v1/document_templates/{$foreignTemplate->id}/duplicate")
            ->assertNotFound();
    });

    it('rejects creating a template for a store outside the caller scope', function () {
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();

        $member = Member::factory()->create();
        $restrictedUser = User::factory()->create(['member_id' => $member->id, 'is_owner' => false, 'is_active' => true]);
        $restrictedUser->givePermissionTo(['document_templates.view', 'document_templates.edit']);
        MemberStore::create(['member_id' => $member->id, 'store_id' => $storeA->id, 'created_at' => now()]);
        Sanctum::actingAs($restrictedUser, ['documents:read', 'documents:write']);

        $this->postJson('/api/v1/document_templates', [
            'store_id' => $storeB->id,
            'name' => 'Foreign',
            'slug' => 'foreign-template',
            'document_type' => 'quote',
            'body_html' => '<p>Nope</p>',
        ])->assertNotFound();
    });
});
