<?php

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentSequence;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

describe('document engine model factories', function () {
    it('creates a document template with json page and branding config', function () {
        $template = DocumentTemplate::factory()->create([
            'branding_config' => [
                'primary_colour' => '#112233',
                'font_family' => 'Inter',
            ],
        ]);

        expect($template->refresh())->toBeInstanceOf(DocumentTemplate::class)
            ->and($template->page_config)->toBeArray()
            ->and($template->branding_config['primary_colour'])->toBe('#112233');
    });

    it('creates a document template version linked to its template', function () {
        $template = DocumentTemplate::factory()->create(['version' => 2]);
        $version = DocumentTemplateVersion::factory()->create([
            'document_template_id' => $template->id,
            'version' => 2,
        ]);

        expect($version->documentTemplate->is($template))->toBeTrue();
    });

    it('creates a document sequence for a store and document type', function () {
        $store = Store::factory()->create();
        $sequence = DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => 'invoice',
            'prefix' => 'INV-',
        ]);

        expect($sequence->store->is($store))->toBeTrue()
            ->and($sequence->document_type)->toBe('invoice')
            ->and($sequence->prefix)->toBe('INV-');
    });

    it('creates a document with polymorphic source and draft status', function () {
        $opportunity = Opportunity::factory()->create();
        $document = Document::factory()->forOpportunity($opportunity)->create();

        expect($document->documentable->is($opportunity))->toBeTrue()
            ->and($document->status)->toBe(DocumentStatus::Draft);
    });

    it('persists store branding columns', function () {
        $store = Store::factory()->create([
            'brand_logo_path' => 'branding/acme/logo.png',
            'brand_primary_colour' => '#AABBCC',
            'brand_secondary_colour' => '#001122',
            'brand_font_family' => 'Helvetica',
        ]);

        expect($store->refresh()->brand_logo_path)->toBe('branding/acme/logo.png')
            ->and($store->brand_font_family)->toBe('Helvetica');
    });
});

describe('document engine relationships', function () {
    it('resolves template store, versions, and documents', function () {
        $store = Store::factory()->create();
        $member = Member::factory()->organisation()->create();
        $template = DocumentTemplate::factory()->for($store)->forMember($member)->create();
        $version = DocumentTemplateVersion::factory()->for($template)->forMember($member)->create([
            'version' => $template->version,
        ]);
        $document = Document::factory()->create([
            'store_id' => $store->id,
            'document_template_id' => $template->id,
        ]);

        $template->refresh();

        expect($template->store->is($store))->toBeTrue()
            ->and($template->createdBy->is($member))->toBeTrue()
            ->and($template->versions->contains(fn ($row) => $row->is($version)))->toBeTrue()
            ->and($template->documents->contains(fn ($row) => $row->is($document)))->toBeTrue();
    });

    it('resolves store document templates, sequences, and documents', function () {
        $store = Store::factory()->create();
        $template = DocumentTemplate::factory()->for($store)->create();
        $sequence = DocumentSequence::factory()->for($store)->create();
        $document = Document::factory()->create(['store_id' => $store->id]);

        $store->refresh();

        expect($store->documentTemplates->contains(fn ($row) => $row->is($template)))->toBeTrue()
            ->and($store->documentSequences->contains(fn ($row) => $row->is($sequence)))->toBeTrue()
            ->and($store->documents->contains(fn ($row) => $row->is($document)))->toBeTrue();
    });

    it('resolves opportunity documents through the morph relation', function () {
        $opportunity = Opportunity::factory()->create();
        $document = Document::factory()->forOpportunity($opportunity)->create();

        expect($opportunity->documents->contains(fn ($row) => $row->is($document)))->toBeTrue()
            ->and($document->documentable->is($opportunity))->toBeTrue();
    });

    it('soft deletes document templates and documents', function () {
        $template = DocumentTemplate::factory()->create();
        $document = Document::factory()->create();

        $template->delete();
        $document->delete();

        expect(DocumentTemplate::query()->whereKey($template->id)->exists())->toBeFalse()
            ->and(DocumentTemplate::withTrashed()->whereKey($template->id)->exists())->toBeTrue()
            ->and(Document::withTrashed()->whereKey($document->id)->exists())->toBeTrue();
    });

    it('resolves document generatedBy user relation', function () {
        $user = User::factory()->create();
        $document = Document::factory()->generatedBy($user)->create();

        expect($document->generatedBy->is($user))->toBeTrue();
    });
});

describe('documents permissions', function () {
    it('seeds the documents permission group', function () {
        $expected = [
            'documents.access',
            'documents.view',
            'documents.create',
            'documents.finalise',
            'documents.share',
            'documents.delete',
            'document_templates.view',
            'document_templates.edit',
        ];

        foreach ($expected as $permission) {
            expect(Permission::findByName($permission, 'web'))->not->toBeNull();
        }
    });
});
