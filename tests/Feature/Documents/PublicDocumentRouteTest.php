<?php

use App\Actions\Documents\GenerateDocument;
use App\Data\Documents\GenerateDocumentData;
use App\Enums\DocumentOutputFormat;
use App\Enums\OpportunityState;
use App\Enums\PricingRoundingDirection;
use App\Models\Document;
use App\Models\DocumentSequence;
use App\Models\DocumentTemplate;
use App\Models\Member;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    Storage::fake('public');
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
    settings()->set('company.date_format_php', 'd/m/Y');
});

/**
 * @return array{document: Document}
 */
function publicDocumentFixture(string $output = 'pdf'): array
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
        'subject' => 'Public Doc Quote',
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

    \Pest\Laravel\actingAs(User::factory()->owner()->create());

    $format = $output === 'html'
        ? DocumentOutputFormat::HtmlDownload
        : DocumentOutputFormat::Pdf;

    $documentData = app(GenerateDocument::class)(new GenerateDocumentData(
        document_template_id: $template->id,
        source_id: $opportunity->id,
        output_format: $format,
    ));

    $document = Document::query()->findOrFail($documentData->id);

    return compact('document');
}

describe('GET public document route', function () {
    it('serves pdf documents inline for valid tokens', function () {
        ['document' => $document] = publicDocumentFixture('pdf');

        $token = 'valid-public-token-pdf';
        $document->update(['public_token' => $token]);

        $response = $this->get(route('documents.public.show', ['token' => $token]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        expect($response->headers->get('Content-Disposition'))->toContain('inline');

        expect($document->refresh()->first_viewed_at)->not->toBeNull();
    });

    it('serves html documents inline for valid tokens', function () {
        ['document' => $document] = publicDocumentFixture('html');

        $token = 'valid-public-token-html';
        $document->update(['public_token' => $token]);

        $this->get(route('documents.public.show', ['token' => $token]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertHeader('Content-Security-Policy', "sandbox; default-src 'none'; style-src 'unsafe-inline'; img-src data:")
            ->assertHeader('X-Frame-Options', 'DENY');
    });

    it('does not sandbox pdf responses', function () {
        ['document' => $document] = publicDocumentFixture();

        $token = 'valid-public-token-pdf-headers';
        $document->update(['public_token' => $token]);

        $this->get(route('documents.public.show', ['token' => $token]))
            ->assertOk()
            ->assertHeaderMissing('Content-Security-Policy');
    });

    it('records first_viewed_at exactly once', function () {
        ['document' => $document] = publicDocumentFixture('pdf');
        $token = 'first-view-token';
        $document->update(['public_token' => $token, 'first_viewed_at' => null]);

        $this->get(route('documents.public.show', ['token' => $token]))->assertOk();
        $firstViewed = $document->refresh()->first_viewed_at;
        expect($firstViewed)->not->toBeNull();

        $this->get(route('documents.public.show', ['token' => $token]))->assertOk();
        expect($document->refresh()->first_viewed_at->equalTo($firstViewed))->toBeTrue();
    });

    it('returns 404 for unknown and revoked tokens without enumeration', function () {
        ['document' => $document] = publicDocumentFixture('pdf');

        $this->get(route('documents.public.show', ['token' => 'totally-unknown-token']))->assertNotFound();

        $document->update(['public_token' => null]);
        $this->get(route('documents.public.show', ['token' => 'revoked-token-example']))->assertNotFound();
    });

    it('shows an expiry page for expired links', function () {
        ['document' => $document] = publicDocumentFixture('pdf');
        $token = 'expired-token';
        $document->update([
            'public_token' => $token,
            'public_expires_at' => now()->subMinute(),
        ]);

        $this->get(route('documents.public.show', ['token' => $token]))
            ->assertStatus(410)
            ->assertSee('expired', false);
    });

    it('requires a pin before serving protected documents and throttles bad attempts', function () {
        ['document' => $document] = publicDocumentFixture('pdf');
        $token = 'pin-protected-token';
        $document->update([
            'public_token' => $token,
            'public_pin_hash' => Hash::make('2468'),
        ]);

        RateLimiter::clear('document-public-pin:'.hash('sha256', $token).':127.0.0.1');

        $this->get(route('documents.public.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Enter PIN', false);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('documents.public.verify-pin', ['token' => $token]), [
                'pin' => '0000',
            ]);
        }

        $this->post(route('documents.public.verify-pin', ['token' => $token]), [
            'pin' => '0000',
        ])->assertStatus(429);

        $this->withSession(['document_public_access.'.hash('sha256', $token) => true])
            ->get(route('documents.public.show', ['token' => $token]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    });
});
