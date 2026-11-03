<?php

declare(strict_types=1);

use App\Domain\Availability\Models\Demand;
use App\Domain\Projects\Models\Project;
use App\Domain\Quotations\Actions\CreateQuotation;
use App\Domain\Quotations\Data\CreateQuotationData;
use App\Domain\Quotations\Enums\QuotationItemType;
use App\Domain\Quotations\Enums\QuotationStatus;
use App\Domain\Quotations\Enums\QuotationVersionStatus;
use App\Domain\Quotations\Enums\QuotationVersionType;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationItem;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Verbs\Events\QuotationCreated;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Thunk\Verbs\Models\VerbEvent;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('creates an independent draft quotation with active version one', function (): void {
    $customer = Member::factory()->organisation()->create();
    $venue = Member::factory()->venue()->create();
    $owner = Member::factory()->user()->create();
    $store = Store::factory()->create();

    $result = app(CreateQuotation::class)(CreateQuotationData::from([
        'subject' => 'Independent festival quotation',
        'store_id' => $store->id,
        'member_id' => $customer->id,
        'venue_id' => $venue->id,
        'owned_by' => $owner->id,
        'starts_at' => '2027-06-18T08:00:00Z',
        'ends_at' => '2027-06-21T23:00:00Z',
        'version_label' => 'Initial proposal',
    ]));

    $quotation = Quotation::query()->with('activeVersion')->findOrFail($result->id);

    expect($quotation->project_id)->toBeNull()
        ->and($quotation->status)->toBe(QuotationStatus::Draft)
        ->and($quotation->number)->toStartWith('QUO-')
        ->and($quotation->member_id)->toBe($customer->id)
        ->and($quotation->version_count)->toBe(1)
        ->and($quotation->activeVersion)->toBeInstanceOf(QuotationVersion::class)
        ->and($quotation->activeVersion?->version_number)->toBe(1)
        ->and($quotation->activeVersion?->version_type)->toBe(QuotationVersionType::Original)
        ->and($quotation->activeVersion?->status)->toBe(QuotationVersionStatus::Draft)
        ->and($quotation->activeVersion?->label)->toBe('Initial proposal')
        ->and(Opportunity::query()->count())->toBe(0)
        ->and(Demand::query()->count())->toBe(0)
        ->and(VerbEvent::query()->where('type', QuotationCreated::class)->count())->toBe(1);
});

it('creates a Project-first quotation without mutating Project metadata', function (): void {
    $customer = Member::factory()->organisation()->create();
    $venue = Member::factory()->venue()->create();
    $owner = Member::factory()->user()->create();
    $store = Store::factory()->create();
    $project = Project::factory()->create([
        'name' => 'Arena project',
        'member_id' => $customer->id,
        'venue_id' => $venue->id,
        'owned_by' => $owner->id,
        'starts_at' => '2027-09-01T08:00:00Z',
        'ends_at' => '2027-09-04T18:00:00Z',
    ]);

    $result = app(CreateQuotation::class)(CreateQuotationData::from([
        'subject' => 'Arena proposal',
        'store_id' => $store->id,
        'project_id' => $project->id,
    ]));

    $quotation = Quotation::query()->findOrFail($result->id);
    $project->refresh();

    expect($quotation->project_id)->toBe($project->id)
        ->and($quotation->member_id)->toBe($customer->id)
        ->and($quotation->venue_id)->toBe($venue->id)
        ->and($quotation->owned_by)->toBe($owner->id)
        ->and($quotation->starts_at?->toIso8601String())->toBe('2027-09-01T08:00:00+00:00')
        ->and($project->name)->toBe('Arena project')
        ->and($project->quotations()->whereKey($quotation->id)->exists())->toBeTrue();
});

it('rejects a customer that differs from the Project customer', function (): void {
    $projectCustomer = Member::factory()->organisation()->create();
    $otherCustomer = Member::factory()->organisation()->create();
    $project = Project::factory()->create(['member_id' => $projectCustomer->id]);
    $store = Store::factory()->create();

    expect(fn () => app(CreateQuotation::class)(CreateQuotationData::from([
        'subject' => 'Wrong customer',
        'store_id' => $store->id,
        'project_id' => $project->id,
        'member_id' => $otherCustomer->id,
    ])))->toThrow(ValidationException::class);

    expect(Quotation::query()->count())->toBe(0)
        ->and(VerbEvent::query()->where('type', QuotationCreated::class)->count())->toBe(0);
});

it('enforces customer, venue, owner, period, and currency invariants in the action boundary', function (array $attributes): void {
    $store = Store::factory()->create();

    expect(fn () => app(CreateQuotation::class)(CreateQuotationData::from([
        'subject' => 'Invalid quotation',
        'store_id' => $store->id,
        ...$attributes,
    ])))->toThrow(ValidationException::class);

    expect(Quotation::query()->count())->toBe(0);
})->with([
    'venue used as customer' => fn (): array => ['member_id' => Member::factory()->venue()->create()->id],
    'organisation used as venue' => fn (): array => [
        'member_id' => Member::factory()->organisation()->create()->id,
        'venue_id' => Member::factory()->organisation()->create()->id,
    ],
    'organisation used as owner' => fn (): array => [
        'member_id' => Member::factory()->organisation()->create()->id,
        'owned_by' => Member::factory()->organisation()->create()->id,
    ],
    'inverted period' => fn (): array => [
        'member_id' => Member::factory()->organisation()->create()->id,
        'starts_at' => '2027-08-04T18:00:00Z',
        'ends_at' => '2027-08-01T09:00:00Z',
    ],
    'invalid currency' => fn (): array => [
        'member_id' => Member::factory()->organisation()->create()->id,
        'currency_code' => 'XYZ',
    ],
]);

it('keeps quotation, version, and item factory relationships canonical', function (): void {
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

    expect($quotation->refresh()->activeVersion?->is($version))->toBeTrue()
        ->and($version->items()->whereKey($item->id)->exists())->toBeTrue()
        ->and($item->quotation->is($quotation))->toBeTrue()
        ->and($item->item_type)->toBe(QuotationItemType::Product);
});

it('requires the quotations.create permission', function (): void {
    $this->actingAs(User::factory()->create());
    $customer = Member::factory()->organisation()->create();
    $store = Store::factory()->create();

    expect(fn () => app(CreateQuotation::class)(CreateQuotationData::from([
        'subject' => 'Forbidden quotation',
        'store_id' => $store->id,
        'member_id' => $customer->id,
    ])))->toThrow(AuthorizationException::class);
});
