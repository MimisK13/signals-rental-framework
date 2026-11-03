<?php

declare(strict_types=1);

use App\Domain\Products\Models\Product;
use App\Domain\Projects\Models\Project;
use App\Domain\Quotations\Actions\AddQuotationItem;
use App\Domain\Quotations\Actions\CreateQuotation;
use App\Domain\Quotations\Data\AddQuotationItemData;
use App\Domain\Quotations\Data\CreateQuotationData;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationItem;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Presentation\Livewire\Editor;
use App\Domain\Quotations\Presentation\Livewire\Form;
use App\Domain\Quotations\Presentation\Livewire\Index;
use App\Models\Member;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('serves native quotation index, create, Project-create, and editor routes', function (): void {
    $project = Project::factory()->create();
    $customer = Member::factory()->organisation()->create();
    $store = Store::factory()->create();
    $created = app(CreateQuotation::class)(CreateQuotationData::from([
        'subject' => 'Native quotation page',
        'store_id' => $store->id,
        'member_id' => $customer->id,
    ]));

    $this->withoutVite()->get(route('quotations.index'))->assertOk()->assertSeeLivewire(Index::class);
    $this->withoutVite()->get(route('quotations.create'))->assertOk()->assertSeeLivewire(Form::class);
    $this->withoutVite()->get(route('projects.quotations.create', $project))->assertOk()->assertSeeLivewire(Form::class);
    $this->withoutVite()->get(route('quotations.edit', $created->id))->assertOk()->assertSeeLivewire(Editor::class);
});

it('creates a standalone quotation from the native form', function (): void {
    $customer = Member::factory()->organisation()->create();
    $store = Store::factory()->create(['is_default' => true]);

    Livewire::test(Form::class)
        ->set('subject', 'Created from native form')
        ->set('memberId', $customer->id)
        ->set('storeId', $store->id)
        ->set('currencyCode', 'GBP')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $quotation = Quotation::query()->where('subject', 'Created from native form')->firstOrFail();

    expect($quotation->project_id)->toBeNull()
        ->and($quotation->versions()->count())->toBe(1);
});

it('prefills Project context on the native Project-first form', function (): void {
    $customer = Member::factory()->organisation()->create();
    $venue = Member::factory()->venue()->create();
    $project = Project::factory()->create([
        'name' => 'Project-first form',
        'member_id' => $customer->id,
        'venue_id' => $venue->id,
    ]);
    Store::factory()->create(['is_default' => true]);

    Livewire::test(Form::class, ['project' => $project])
        ->assertSet('projectId', $project->id)
        ->assertSet('subject', 'Project-first form')
        ->assertSet('memberId', $customer->id)
        ->assertSet('venueId', $venue->id);
});

it('renders and mutates the canonical editor contract', function (): void {
    $customer = Member::factory()->organisation()->create();
    $store = Store::factory()->create();
    $created = app(CreateQuotation::class)(CreateQuotationData::from([
        'subject' => 'Canonical editor',
        'store_id' => $store->id,
        'member_id' => $customer->id,
        'starts_at' => '2027-12-01T08:00:00Z',
        'ends_at' => '2027-12-02T08:00:00Z',
    ]));
    $quotation = Quotation::query()->findOrFail($created->id);
    $product = Product::factory()->create();

    $component = Livewire::test(Editor::class, ['quotation' => $quotation])
        ->assertOk()
        ->assertSee('canonicalQuotationEditor', escape: false)
        ->call('addProduct', $product->id, 2, null)
        ->assertDispatched('line-items-mutation-done');

    $item = QuotationItem::query()->where('quotation_id', $quotation->id)->sole();

    $component->call('applyFieldMutations', [[
        'id' => $item->id,
        'field' => 'unit_price',
        'value' => '12.50',
    ]])->assertHasNoErrors();

    $component->set('dealPriceInput', '10.00')
        ->set('dealPriceMode', 'flat_headline')
        ->call('applyDealPrice')
        ->assertRedirect();

    expect($item->refresh()->unit_price)->toBe(1250)
        ->and($item->quantity)->toBe('2.00')
        ->and($quotation->refresh()->deal_total)->toBe(1000);
});

it('returns a stale tree response when the editor revision has moved', function (): void {
    $quotation = Quotation::factory()->create();
    $version = QuotationVersion::factory()->create([
        'quotation_id' => $quotation->id,
        'currency_code' => $quotation->currency_code,
    ]);
    $quotation->update(['active_version_id' => $version->id, 'version_count' => 1, 'editor_revision' => 5]);

    Livewire::test(Editor::class, ['quotation' => $quotation->refresh()])
        ->call('persistTree', [], 4)
        ->assertReturned(fn (array $result): bool => $result['stale'] === true && $result['revision'] === 5);
});

it('wires version creation and acceptance through the native editor page', function (): void {
    $customer = Member::factory()->organisation()->create();
    $store = Store::factory()->create();
    $created = app(CreateQuotation::class)(CreateQuotationData::from([
        'subject' => 'Editor lifecycle',
        'store_id' => $store->id,
        'member_id' => $customer->id,
        'starts_at' => '2027-12-01T08:00:00Z',
        'ends_at' => '2027-12-02T08:00:00Z',
    ]));
    $quotation = Quotation::query()->findOrFail($created->id);
    $version = QuotationVersion::query()->findOrFail($quotation->active_version_id);
    app(AddQuotationItem::class)($quotation, $version, AddQuotationItemData::from([
        'name' => 'Lifecycle line',
        'item_type' => 'service',
        'unit_price' => 1000,
    ]));

    Livewire::test(Editor::class, ['quotation' => $quotation->refresh()])
        ->call('createVersion', 'revision')
        ->assertRedirect();

    $quotation->refresh();
    expect($quotation->version_count)->toBe(2)
        ->and($quotation->activeVersion?->items()->count())->toBe(1);

    Livewire::test(Editor::class, ['quotation' => $quotation])
        ->call('acceptActiveVersion')
        ->assertRedirect();

    expect($quotation->refresh()->project_id)->not->toBeNull();
});

it('deletes quotation items through the canonical keepalive route', function (): void {
    $quotation = Quotation::factory()->create();
    $version = QuotationVersion::factory()->create([
        'quotation_id' => $quotation->id,
        'currency_code' => $quotation->currency_code,
    ]);
    $quotation->update(['active_version_id' => $version->id, 'version_count' => 1]);
    $item = QuotationItem::factory()->create([
        'quotation_id' => $quotation->id,
        'version_id' => $version->id,
    ]);

    $this->delete(route('quotations.items.destroy', [$quotation, $item]))->assertNoContent();

    expect(QuotationItem::query()->find($item->id))->toBeNull();
});

arch('Quotations Presentation is native and has no Volt dependency', function (): void {
    expect('App\Domain\Quotations\Presentation')->not->toUse('Livewire\Volt');
});
