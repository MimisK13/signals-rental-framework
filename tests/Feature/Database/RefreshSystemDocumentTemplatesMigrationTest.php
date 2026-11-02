<?php

use App\Domain\Documents\Database\Seeders\DocumentTemplateSeeder;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Models\DocumentTemplateVersion;
use App\Domain\Documents\Services\SystemDocumentTemplates;
use App\Models\Store;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->store = Store::factory()->create();
    app(DocumentTemplateSeeder::class)->seedForStore($this->store);
});

function refreshSystemDocumentTemplatesMigration(): object
{
    $snapshot = require base_path('app/Domain/Documents/Database/Migrations/2026_07_03_181108_refresh_system_document_templates_content.php');
    $refresh = require base_path('app/Domain/Documents/Database/Migrations/2026_07_03_181109_apply_system_document_templates_content.php');
    $snapshot->up();

    return $refresh;
}

it('refreshes stale system templates and snapshots the prior content', function () {
    $template = DocumentTemplate::query()
        ->where('store_id', $this->store->id)
        ->where('document_type', 'quote')
        ->firstOrFail();

    $canonical = SystemDocumentTemplates::definition('quote');
    $staleBody = '<p>Legacy flat quote body without line items</p>';
    $originalVersion = $template->version;

    $template->update([
        'body_html' => $staleBody,
        'version' => $originalVersion,
    ]);

    expect(DocumentTemplateVersion::query()
        ->where('document_template_id', $template->id)
        ->count())->toBe(0);

    refreshSystemDocumentTemplatesMigration()->up();

    $template->refresh();

    expect($template->body_html)->toBe($canonical['body_html'])
        ->and($template->body_html)->toContain('@foreach ($items as $item)')
        ->and($template->version)->toBe($originalVersion + 1);

    $snapshot = DocumentTemplateVersion::query()
        ->where('document_template_id', $template->id)
        ->where('version', $originalVersion)
        ->first();

    expect($snapshot)->not->toBeNull()
        ->and($snapshot->body_html)->toBe($staleBody)
        ->and($snapshot->change_note)->toBe('Pre-migration snapshot before system template refresh');
});

it('leaves already-current system templates untouched', function () {
    $template = DocumentTemplate::query()
        ->where('store_id', $this->store->id)
        ->where('document_type', 'invoice')
        ->firstOrFail();

    $originalVersion = $template->version;
    $versionCountBefore = DocumentTemplateVersion::query()
        ->where('document_template_id', $template->id)
        ->count();

    refreshSystemDocumentTemplatesMigration()->up();

    $template->refresh();

    expect($template->version)->toBe($originalVersion)
        ->and(DocumentTemplateVersion::query()
            ->where('document_template_id', $template->id)
            ->count())->toBe($versionCountBefore);
});

it('registers the refresh migration in the migration table', function () {
    expect(Artisan::call('migrate:status', [
        '--path' => 'app/Domain/Documents/Database/Migrations/2026_07_03_181108_refresh_system_document_templates_content.php',
    ]))->toBe(0);
});
