<?php

use App\Enums\DocumentOutputFormat;
use App\Enums\DocumentStatus;
use App\Enums\OpportunityState;
use App\Enums\PricingRoundingDirection;
use App\Jobs\BatchGenerateDocumentsJob;
use App\Jobs\GenerateDocumentJob;
use App\Models\Document;
use App\Models\DocumentSequence;
use App\Models\DocumentTemplate;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OrganisationTaxClass;
use App\Models\Product;
use App\Models\ProductTaxClass;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    Storage::fake('public');
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
    settings()->set('company.date_format_php', 'd/m/Y');
});

/**
 * @return array{store: Store, opportunity: Opportunity, template: DocumentTemplate}
 */
function documentApiFixture(): array
{
    $orgClass = OrganisationTaxClass::factory()->create();
    $productClass = ProductTaxClass::factory()->create(['is_default' => true]);
    $taxRate = TaxRate::factory()->create(['rate' => '20.0000', 'is_active' => true]);

    TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass->id,
        'tax_rate_id' => $taxRate->id,
        'is_active' => true,
    ]);

    $store = Store::factory()->create();
    $member = Member::factory()->organisation()->create(['sale_tax_class_id' => $orgClass->id]);
    $product = Product::factory()->sale()->create(['tax_class_id' => $productClass->id]);

    $opportunity = Opportunity::factory()->create([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'subject' => 'Document API Quote',
        'currency_code' => 'GBP',
        'state' => OpportunityState::Quotation,
        'starts_at' => Carbon::parse('2026-07-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-07-03T17:00:00Z'),
        'charge_total' => 10_000,
        'tax_total' => 2_000,
    ]);

    OpportunityItem::factory()->sale()->create([
        'opportunity_id' => $opportunity->id,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'name' => 'Line',
        'quantity' => '1',
        'unit_price' => 10_000,
        'total' => 10_000,
    ]);

    $template = DocumentTemplate::factory()->for($store)->create([
        'document_type' => 'quote',
        'model_type' => Opportunity::class,
        'body_html' => '<h1>{{ $opportunity->subject }}</h1>',
        'is_default' => true,
        'is_active' => true,
    ]);

    DocumentSequence::factory()->create([
        'store_id' => $store->id,
        'document_type' => 'quote',
        'prefix' => 'QUO-',
        'include_year' => false,
        'padding' => 4,
        'next_number' => 1,
    ]);

    return compact('store', 'opportunity', 'template');
}

/**
 * @param  list<string>  $permissions
 */
function actingAsDocumentApiUser(array $permissions = [], ?Store $store = null): User
{
    $user = User::factory()->create(['is_owner' => false, 'is_active' => true]);
    $user->givePermissionTo(array_merge([
        'documents.view',
        'documents.create',
        'documents.finalise',
        'documents.share',
        'documents.delete',
        'opportunities.view',
    ], $permissions));

    if ($store !== null) {
        $member = Member::factory()->create();
        $user->update(['member_id' => $member->id]);
        MemberStore::create(['member_id' => $member->id, 'store_id' => $store->id, 'created_at' => now()]);
    }

    Sanctum::actingAs($user, ['documents:read', 'documents:write', 'opportunities:read']);

    return $user;
}

describe('Document API', function () {
    it('lists documents with ransack filters and store scoping', function () {
        ['store' => $storeA, 'opportunity' => $opportunityA, 'template' => $templateA] = documentApiFixture();
        ['store' => $storeB, 'opportunity' => $opportunityB, 'template' => $templateB] = documentApiFixture();

        actingAsDocumentApiUser(store: $storeA);

        $this->postJson('/api/v1/documents/generate', [
            'document_template_id' => $templateA->id,
            'documentable_type' => 'opportunity',
            'documentable_id' => $opportunityA->id,
        ])->assertCreated();

        $this->postJson('/api/v1/documents/generate', [
            'document_template_id' => $templateB->id,
            'documentable_type' => 'opportunity',
            'documentable_id' => $opportunityB->id,
        ])->assertNotFound();

        $response = $this->getJson('/api/v1/documents?q[document_type_eq]=quote')
            ->assertOk();

        expect(collect($response->json('documents')))->toHaveCount(1)
            ->and($response->json('documents.0.store_id'))->toBe($storeA->id);
    });

    it('shows a document with template and public link metadata', function () {
        ['store' => $store, 'opportunity' => $opportunity, 'template' => $template] = documentApiFixture();
        actingAsDocumentApiUser(store: $store);

        $created = $this->postJson('/api/v1/documents/generate', [
            'document_template_id' => $template->id,
            'documentable_type' => 'opportunity',
            'documentable_id' => $opportunity->id,
        ])->assertCreated()->json('document');

        $this->postJson("/api/v1/documents/{$created['id']}/public_link", [
            'pin' => '1234',
        ])->assertOk();

        $this->getJson("/api/v1/documents/{$created['id']}")
            ->assertOk()
            ->assertJsonPath('document.template.id', $template->id)
            ->assertJsonPath('document.public_link.is_active', true)
            ->assertJsonPath('document.public_link.is_pin_protected', true)
            ->assertJsonPath('document.download_url', fn ($url) => is_string($url) && $url !== '')
            ->assertJsonPath('document.download_url', fn ($url) => str_contains($url, '/documents/'.$created['id'].'/file'));
    });

    it('generates a document synchronously and supports html output', function () {
        ['store' => $store, 'opportunity' => $opportunity, 'template' => $template] = documentApiFixture();
        actingAsDocumentApiUser(store: $store);

        $this->postJson('/api/v1/documents/generate', [
            'document_template_id' => $template->id,
            'documentable_type' => 'opportunity',
            'documentable_id' => $opportunity->id,
            'output_format' => DocumentOutputFormat::HtmlDownload->value,
        ])->assertCreated()
            ->assertJsonPath('document.html_snapshot_path', fn ($path) => is_string($path) && $path !== '')
            ->assertJsonPath('document.pdf_path', null);
    });

    it('queues async generation on the exports queue with a job id', function () {
        Queue::fake();
        ['store' => $store, 'opportunity' => $opportunity, 'template' => $template] = documentApiFixture();
        actingAsDocumentApiUser(store: $store);

        $response = $this->postJson('/api/v1/documents/generate?async=1', [
            'document_template_id' => $template->id,
            'documentable_type' => 'opportunity',
            'documentable_id' => $opportunity->id,
        ])->assertAccepted();

        expect($response->json('job_id'))->not->toBeEmpty();

        Queue::assertPushedOn('exports', GenerateDocumentJob::class);
    });

    it('queues batch generation on the exports queue', function () {
        Queue::fake();
        ['store' => $store, 'opportunity' => $opportunity, 'template' => $template] = documentApiFixture();
        actingAsDocumentApiUser(store: $store);

        $response = $this->postJson('/api/v1/documents/batch_generate', [
            'sources' => [[
                'documentable_type' => 'opportunity',
                'documentable_id' => $opportunity->id,
                'document_template_id' => $template->id,
            ]],
        ])->assertAccepted();

        expect($response->json('job_id'))->not->toBeEmpty();
        Queue::assertPushedOn('exports', BatchGenerateDocumentsJob::class);
    });

    it('finalises and voids documents with guards', function () {
        ['store' => $store, 'opportunity' => $opportunity, 'template' => $template] = documentApiFixture();
        actingAsDocumentApiUser(store: $store);

        $documentId = $this->postJson('/api/v1/documents/generate', [
            'document_template_id' => $template->id,
            'documentable_type' => 'opportunity',
            'documentable_id' => $opportunity->id,
        ])->json('document.id');

        $this->postJson("/api/v1/documents/{$documentId}/finalise")
            ->assertOk()
            ->assertJsonPath('document.status', DocumentStatus::Final->value);

        $this->postJson("/api/v1/documents/{$documentId}/void")
            ->assertOk()
            ->assertJsonPath('document.status', DocumentStatus::Void->value)
            ->assertJsonPath('document.public_link.is_active', false);
    });

    it('prevents voiding draft documents', function () {
        ['store' => $store, 'opportunity' => $opportunity, 'template' => $template] = documentApiFixture();
        actingAsDocumentApiUser(store: $store);

        $documentId = $this->postJson('/api/v1/documents/generate', [
            'document_template_id' => $template->id,
            'documentable_type' => 'opportunity',
            'documentable_id' => $opportunity->id,
        ])->json('document.id');

        $this->postJson("/api/v1/documents/{$documentId}/void")
            ->assertForbidden();
    });

    it('manages public links via create configure and revoke endpoints', function () {
        ['store' => $store, 'opportunity' => $opportunity, 'template' => $template] = documentApiFixture();
        actingAsDocumentApiUser(store: $store);

        $documentId = $this->postJson('/api/v1/documents/generate', [
            'document_template_id' => $template->id,
            'documentable_type' => 'opportunity',
            'documentable_id' => $opportunity->id,
        ])->json('document.id');

        $this->postJson("/api/v1/documents/{$documentId}/public_link", [
            'expires_at' => now()->addDay()->toIso8601String(),
            'pin' => '9876',
        ])->assertOk()
            ->assertJsonPath('document.public_link.is_active', true);

        $this->putJson("/api/v1/documents/{$documentId}/public_link", [
            'clear_pin' => true,
        ])->assertOk()
            ->assertJsonPath('document.public_link.is_pin_protected', false);

        $this->deleteJson("/api/v1/documents/{$documentId}/public_link")
            ->assertOk()
            ->assertJsonPath('document.public_link.is_active', false);
    });

    it('blocks store-scoped idor on show generate finalise and void', function () {
        ['store' => $storeA, 'opportunity' => $opportunityA, 'template' => $templateA] = documentApiFixture();
        ['store' => $storeB, 'opportunity' => $opportunityB, 'template' => $templateB] = documentApiFixture();

        actingAsDocumentApiUser(store: $storeA);

        $this->postJson('/api/v1/documents/generate', [
            'document_template_id' => $templateB->id,
            'documentable_type' => 'opportunity',
            'documentable_id' => $opportunityB->id,
        ])->assertNotFound();

        $ownedDocumentId = $this->postJson('/api/v1/documents/generate', [
            'document_template_id' => $templateA->id,
            'documentable_type' => 'opportunity',
            'documentable_id' => $opportunityA->id,
        ])->json('document.id');

        $otherStoreDocument = Document::factory()->forOpportunity($opportunityB)->create([
            'store_id' => $storeB->id,
        ]);

        $this->getJson("/api/v1/documents/{$otherStoreDocument->id}")->assertNotFound();
        $this->postJson("/api/v1/documents/{$otherStoreDocument->id}/finalise")->assertNotFound();
        $this->postJson("/api/v1/documents/{$otherStoreDocument->id}/void")->assertNotFound();

        expect($ownedDocumentId)->toBeInt();
    });
});
