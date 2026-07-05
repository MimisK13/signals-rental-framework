<?php

use App\Actions\Documents\ExportDocumentTemplate;
use App\Actions\Documents\ImportDocumentTemplate;
use App\Data\Documents\ImportDocumentTemplateData;
use App\Models\DocumentTemplate;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

describe('document template export/import', function () {
    it('exports template content and settings without version history', function () {
        $template = DocumentTemplate::factory()->create([
            'name' => 'Export Me',
            'description' => 'Portable template',
            'document_type' => 'quote',
            'model_type' => Opportunity::class,
            'body_html' => '<p>Export body</p>',
            'header_html' => '<header>Header</header>',
            'footer_html' => '<footer>Footer</footer>',
            'styles_css' => '.doc { color: red; }',
            'page_config' => ['page_size' => 'A4'],
            'branding_config' => ['primary_colour' => '#000000'],
            'version' => 5,
        ]);

        $export = (new ExportDocumentTemplate)($template);

        expect($export['export_format'])->toBe('signals.document_template')
            ->and($export['export_version'])->toBe(1)
            ->and($export['name'])->toBe('Export Me')
            ->and($export['body_html'])->toBe('<p>Export body</p>')
            ->and($export['page_config']['page_size'])->toBe('A4')
            ->and($export)->not->toHaveKey('version')
            ->and($export)->not->toHaveKey('versions');
    });

    it('imports a valid export as a non-system template', function () {
        $store = Store::factory()->create();
        $template = DocumentTemplate::factory()->for($store)->create([
            'document_type' => 'quote',
            'model_type' => Opportunity::class,
        ]);

        $export = (new ExportDocumentTemplate)($template);
        $export['name'] = 'Imported Quote';

        $result = (new ImportDocumentTemplate)(ImportDocumentTemplateData::from([
            'store_id' => $store->id,
            'payload' => $export,
        ]));

        expect($result->name)->toBe('Imported Quote')
            ->and($result->is_system)->toBeFalse()
            ->and($result->body_html)->toBe($template->body_html);
    });

    it('round-trips export and import losslessly for content fields', function () {
        $store = Store::factory()->create();
        $source = DocumentTemplate::factory()->for($store)->create([
            'document_type' => 'pick_list',
            'model_type' => Opportunity::class,
            'body_html' => '<p>Pick {{ $opportunity->subject }}</p>',
            'header_html' => '<h1>Pick</h1>',
            'footer_html' => '<small>Footer</small>',
            'styles_css' => '.pick { font-size: 12px; }',
            'page_config' => ['page_size' => 'Letter', 'page_orientation' => 'landscape'],
            'branding_config' => ['primary_colour' => '#112233'],
            'is_active' => true,
        ]);

        $export = (new ExportDocumentTemplate)($source);

        $imported = (new ImportDocumentTemplate)(ImportDocumentTemplateData::from([
            'store_id' => $store->id,
            'payload' => $export,
            'slug' => 'imported-pick-list',
        ]));

        $reExported = (new ExportDocumentTemplate)(DocumentTemplate::query()->findOrFail($imported->id));

        expect($reExported)->toMatchArray([
            'export_format' => $export['export_format'],
            'export_version' => $export['export_version'],
            'name' => $export['name'],
            'description' => $export['description'],
            'document_type' => $export['document_type'],
            'model_type' => $export['model_type'],
            'body_html' => $export['body_html'],
            'header_html' => $export['header_html'],
            'footer_html' => $export['footer_html'],
            'styles_css' => $export['styles_css'],
            'page_config' => $export['page_config'],
            'branding_config' => $export['branding_config'],
            'is_active' => $export['is_active'],
        ]);
    });

    it('returns 422 validation errors for malformed import payloads via API', function () {
        $store = Store::factory()->create();
        Sanctum::actingAs(User::factory()->owner()->create(), ['documents:write']);

        $this->postJson('/api/v1/document_templates/import', [
            'store_id' => $store->id,
            'payload' => ['name' => 'Broken'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['export_format', 'document_type', 'model_type', 'body_html']);
    });

    it('returns 422 when model_type does not match the registered document type source model', function () {
        $store = Store::factory()->create();
        Sanctum::actingAs(User::factory()->owner()->create(), ['documents:write']);

        $template = DocumentTemplate::factory()->for($store)->create([
            'document_type' => 'quote',
            'model_type' => Opportunity::class,
            'body_html' => '<p>Quote body</p>',
        ]);

        $export = (new ExportDocumentTemplate)($template);
        $export['document_type'] = 'statement';
        $export['model_type'] = Opportunity::class;

        $this->postJson('/api/v1/document_templates/import', [
            'store_id' => $store->id,
            'payload' => $export,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['model_type']);
    });

    it('exports and imports through the API', function () {
        $store = Store::factory()->create();
        Sanctum::actingAs(User::factory()->owner()->create(), ['documents:read', 'documents:write']);

        $template = DocumentTemplate::factory()->for($store)->create([
            'document_type' => 'quote',
            'model_type' => Opportunity::class,
            'body_html' => '<p>API export</p>',
        ]);

        $export = $this->getJson("/api/v1/document_templates/{$template->id}/export")
            ->assertOk()
            ->json('document_template_export');

        $export['name'] = 'API Imported';

        $this->postJson('/api/v1/document_templates/import', [
            'store_id' => $store->id,
            'payload' => $export,
        ])->assertCreated()
            ->assertJsonPath('document_template.name', 'API Imported')
            ->assertJsonPath('document_template.is_system', false);
    });
});
