<?php

use App\Domain\Documents\Database\Seeders\DocumentTemplateSeeder;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Services\SystemDocumentTemplates;
use App\Models\Store;

beforeEach(function () {
    $this->store = Store::factory()->create();
    app(DocumentTemplateSeeder::class)->seedForStore($this->store);
});

function refreshReturnListStatementMigration(): object
{
    $snapshot = require base_path('app/Domain/Documents/Database/Migrations/2026_07_03_200000_refresh_system_document_templates_return_list_statement.php');
    $refresh = require base_path('app/Domain/Documents/Database/Migrations/2026_07_03_200001_apply_system_document_templates_return_list_statement.php');
    $snapshot->up();

    return $refresh;
}

it('seeds return_list and statement templates for existing stores via migration', function () {
    DocumentTemplate::query()
        ->where('store_id', $this->store->id)
        ->whereIn('document_type', ['return_list', 'statement'])
        ->forceDelete();

    expect(DocumentTemplate::query()
        ->where('store_id', $this->store->id)
        ->where('document_type', 'return_list')
        ->exists())->toBeFalse();

    refreshReturnListStatementMigration()->up();

    foreach (['return_list', 'statement'] as $type) {
        $template = DocumentTemplate::query()
            ->where('store_id', $this->store->id)
            ->where('document_type', $type)
            ->first();

        expect($template)->not->toBeNull()
            ->and($template->is_system)->toBeTrue()
            ->and($template->body_html)->toBe(SystemDocumentTemplates::definition($type)['body_html']);
    }
});
