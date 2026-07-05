<?php

use App\Actions\Documents\RenderDocumentPreview;
use App\Data\Documents\RenderDocumentPreviewData;
use App\Models\DocumentTemplate;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

/**
 * @return array{store: Store, opportunity: Opportunity, template: DocumentTemplate}
 */
function previewFixture(): array
{
    $store = Store::factory()->create();
    $opportunity = Opportunity::factory()->for($store)->create([
        'subject' => 'Preview Subject Line',
    ]);
    $template = DocumentTemplate::factory()->for($store)->create([
        'document_type' => 'quote',
        'model_type' => Opportunity::class,
        'body_html' => '<h1>{{ $opportunity->subject }}</h1>',
    ]);

    return compact('store', 'opportunity', 'template');
}

describe('RenderDocumentPreview', function () {
    it('previews a document when the caller can access the template and source', function () {
        ['opportunity' => $opportunity, 'template' => $template] = previewFixture();

        $this->actingAs(User::factory()->owner()->create());

        $preview = app(RenderDocumentPreview::class)(new RenderDocumentPreviewData(
            document_template_id: $template->id,
            source_id: $opportunity->id,
        ));

        expect($preview->html)->toContain('Preview Subject Line')
            ->and($preview->rendered_data['opportunity']['subject'])->toBe('Preview Subject Line');
    });

    it('rejects preview when the caller cannot view the source opportunity', function () {
        ['store' => $store, 'opportunity' => $opportunity, 'template' => $template] = previewFixture();

        $member = Member::factory()->create();
        $user = User::factory()->create(['member_id' => $member->id, 'is_owner' => false, 'is_active' => true]);
        $user->givePermissionTo('document_templates.view');
        MemberStore::create(['member_id' => $member->id, 'store_id' => $store->id, 'created_at' => now()]);

        $this->actingAs($user);

        expect(fn () => app(RenderDocumentPreview::class)(new RenderDocumentPreviewData(
            document_template_id: $template->id,
            source_id: $opportunity->id,
        )))->toThrow(AuthorizationException::class);
    });

    it('rejects preview when the source opportunity belongs to a different store than the template', function () {
        ['template' => $template] = previewFixture();
        $foreignStore = Store::factory()->create();
        $foreignOpportunity = Opportunity::factory()->for($foreignStore)->create([
            'subject' => 'Foreign Opportunity',
        ]);

        expect($foreignOpportunity->store_id)->not->toBe($template->store_id);

        $this->actingAs(User::factory()->owner()->create());

        expect(fn () => app(RenderDocumentPreview::class)(new RenderDocumentPreviewData(
            document_template_id: $template->id,
            source_id: $foreignOpportunity->id,
        )))->toThrow(ValidationException::class, 'The source record does not belong to the template store.');
    });
});
