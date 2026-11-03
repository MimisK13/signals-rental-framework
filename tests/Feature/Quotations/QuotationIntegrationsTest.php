<?php

declare(strict_types=1);

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Documents\Data\GenerateDocumentData;
use App\Domain\Documents\Services\DocumentResolverRegistry;
use App\Domain\Documents\Services\DocumentTypeRegistry;
use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Services\EventNotificationMapRegistry;
use App\Domain\Notifications\Services\NotificationRegistry;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationItem;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Services\QuotationDataResolver;
use App\Domain\Stores\Models\Store;
use App\Domain\Webhooks\Services\WebhookEventRegistry;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
});

it('protects the canonical quotation API with permissions and token abilities', function (): void {
    $user = User::factory()->owner()->create();

    $this->getJson('/api/v1/quotations')->assertUnauthorized();

    $wrongToken = $user->createToken('quotation-test', ['projects:read'])->plainTextToken;
    $this->withToken($wrongToken)->getJson('/api/v1/quotations')->assertForbidden();
});

it('lists quotations with the canonical read ability', function (): void {
    $user = User::factory()->owner()->create();
    $readToken = $user->createToken('quotation-test', ['quotations:read'])->plainTextToken;
    $this->withToken($readToken)->getJson('/api/v1/quotations')->assertOk()->assertJsonPath('meta.total', 0);
});

it('creates standalone quotations through the canonical API', function (): void {
    $user = User::factory()->owner()->create();
    $token = $user->createToken('quotation-test', ['quotations:write'])->plainTextToken;
    $store = Store::factory()->create();
    $customer = Member::factory()->organisation()->create();

    $this->withToken($token)->postJson('/api/v1/quotations', [
        'subject' => 'API quotation',
        'store_id' => $store->id,
        'member_id' => $customer->id,
        'currency_code' => 'GBP',
    ])->assertCreated()->assertJsonPath('quotation.subject', 'API quotation');

    expect(Quotation::query()->where('subject', 'API quotation')->sole()->versions()->count())->toBe(1);
});

it('resolves canonical quotation document data from the active version', function (): void {
    $quotation = Quotation::factory()->create(['charge_excluding_tax_total' => 1000, 'tax_total' => 200, 'charge_including_tax_total' => 1200]);
    $version = QuotationVersion::factory()->create(['quotation_id' => $quotation->id, 'currency_code' => $quotation->currency_code]);
    $quotation->update(['active_version_id' => $version->id, 'version_count' => 1]);
    QuotationItem::factory()->create([
        'quotation_id' => $quotation->id,
        'version_id' => $version->id,
        'name' => 'Document line',
        'unit_price' => 1000,
        'total' => 1000,
        'tax_total' => 200,
        'gross_total' => 1200,
    ]);

    $registry = app(DocumentResolverRegistry::class);
    $data = $registry->resolve('quotation', $quotation->id);

    expect($registry->get('quotation'))->toBeInstanceOf(QuotationDataResolver::class)
        ->and(app(DocumentTypeRegistry::class)->resolverType('quotation'))->toBe('quotation')
        ->and(app(DocumentTypeRegistry::class)->modelType('quotation'))->toBe(Quotation::class)
        ->and($data['quotation']['number'])->toBe($quotation->number)
        ->and($data['quotation']['total'])->toBe('12.00')
        ->and($data['items'][0]['name'])->toBe('Document line')
        ->and(GenerateDocumentData::from(['documentable_type' => 'quotation', 'source_id' => $quotation->id])->resolvedDocumentableClass())->toBe(Quotation::class);
});

it('registers quotation notifications and webhook events', function (): void {
    foreach (['quotation.sent', 'quotation.accepted', 'quotation.declined'] as $name) {
        expect(app(WebhookEventRegistry::class)->has($name))->toBeTrue()
            ->and(app(EventNotificationMapRegistry::class)->has($name))->toBeTrue()
            ->and(app(NotificationRegistry::class)->get($name))->not->toBeNull();
    }
});

it('removes the experimental quotation routes', function (): void {
    expect(app('router')->getRoutes()->getByName('experimental.quotations.index'))->toBeNull()
        ->and(app('router')->getRoutes()->getByName('experimental.quotations.edit'))->toBeNull();
});
