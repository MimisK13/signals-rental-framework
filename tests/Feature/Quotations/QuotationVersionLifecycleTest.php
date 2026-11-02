<?php

declare(strict_types=1);

use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Domain\Quotations\Actions\AcceptQuotationVersion;
use App\Domain\Quotations\Actions\ActivateQuotationVersion;
use App\Domain\Quotations\Actions\CreateQuotation;
use App\Domain\Quotations\Actions\CreateQuotationVersion;
use App\Domain\Quotations\Actions\DeclineQuotationVersion;
use App\Domain\Quotations\Actions\SendQuotationVersion;
use App\Domain\Quotations\Data\AcceptQuotationVersionData;
use App\Domain\Quotations\Data\CreateQuotationData;
use App\Domain\Quotations\Data\CreateQuotationVersionData;
use App\Domain\Quotations\Data\DeclineQuotationVersionData;
use App\Domain\Quotations\Data\SendQuotationVersionData;
use App\Domain\Quotations\Enums\QuotationStatus;
use App\Domain\Quotations\Enums\QuotationVersionStatus;
use App\Domain\Quotations\Enums\QuotationVersionType;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationItem;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Models\Demand;
use App\Models\Member;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

/**
 * @return array{quotation: Quotation, version: QuotationVersion, customer: Member, store: Store}
 */
function createQuotationForLifecycle(bool $withItem = true, ?Project $project = null): array
{
    $customer = $project?->customer ?? Member::factory()->organisation()->create();
    $store = Store::factory()->create();
    $result = app(CreateQuotation::class)(CreateQuotationData::from([
        'subject' => 'Lifecycle quotation',
        'store_id' => $store->id,
        'project_id' => $project?->id,
        'member_id' => $project === null ? $customer->id : null,
        'starts_at' => '2027-11-10T08:00:00Z',
        'ends_at' => '2027-11-12T18:00:00Z',
    ]));

    $quotation = Quotation::query()->findOrFail($result->id);
    $version = QuotationVersion::query()->findOrFail($quotation->active_version_id);

    if ($withItem) {
        QuotationItem::factory()->create([
            'quotation_id' => $quotation->id,
            'version_id' => $version->id,
            'total' => 25000,
        ]);
        $version->update([
            'charge_excluding_tax_total' => 25000,
            'charge_including_tax_total' => 25000,
            'charge_total' => 25000,
        ]);
        $version->refresh();
    }

    return compact('quotation', 'version', 'customer', 'store');
}

it('creates revisions and alternatives as new active versions', function (): void {
    ['quotation' => $quotation, 'version' => $original] = createQuotationForLifecycle();

    $revisionData = app(CreateQuotationVersion::class)($quotation, CreateQuotationVersionData::from([
        'version_type' => QuotationVersionType::Revision->value,
        'label' => 'Revision 2',
    ]));

    $revision = QuotationVersion::query()->findOrFail($revisionData->id);
    $quotation->refresh();

    expect($revision->version_number)->toBe(2)
        ->and($revision->version_type)->toBe(QuotationVersionType::Revision)
        ->and($revision->parent_version_id)->toBe($original->id)
        ->and($revision->is_active)->toBeTrue()
        ->and($original->refresh()->status)->toBe(QuotationVersionStatus::Superseded)
        ->and($quotation->active_version_id)->toBe($revision->id)
        ->and($quotation->version_count)->toBe(2)
        ->and($quotation->status)->toBe(QuotationStatus::Draft);

    $alternativeData = app(CreateQuotationVersion::class)($quotation, CreateQuotationVersionData::from([
        'version_type' => QuotationVersionType::Alternative->value,
        'label' => 'Alternative 3',
    ]));
    $alternative = QuotationVersion::query()->findOrFail($alternativeData->id);

    expect($alternative->version_number)->toBe(3)
        ->and($alternative->version_type)->toBe(QuotationVersionType::Alternative)
        ->and($alternative->parent_version_id)->toBeNull()
        ->and($revision->refresh()->status)->toBe(QuotationVersionStatus::Draft)
        ->and($revision->is_active)->toBeFalse();
});

it('activates another live version and mirrors its commercial totals', function (): void {
    ['quotation' => $quotation, 'version' => $original] = createQuotationForLifecycle();
    $alternativeData = app(CreateQuotationVersion::class)($quotation, CreateQuotationVersionData::from([
        'version_type' => QuotationVersionType::Alternative->value,
    ]));
    $alternative = QuotationVersion::query()->findOrFail($alternativeData->id);
    $original->update([
        'charge_excluding_tax_total' => 12000,
        'charge_including_tax_total' => 12000,
        'charge_total' => 12000,
    ]);

    app(ActivateQuotationVersion::class)($quotation->refresh(), $original->refresh());

    expect($quotation->refresh()->active_version_id)->toBe($original->id)
        ->and($quotation->charge_total)->toBe(12000)
        ->and($original->refresh()->is_active)->toBeTrue()
        ->and($alternative->refresh()->is_active)->toBeFalse();
});

it('requires dates and a priceable item before sending', function (): void {
    $customer = Member::factory()->organisation()->create();
    $store = Store::factory()->create();
    $created = app(CreateQuotation::class)(CreateQuotationData::from([
        'subject' => 'Incomplete quotation',
        'store_id' => $store->id,
        'member_id' => $customer->id,
    ]));
    $quotation = Quotation::query()->findOrFail($created->id);
    $version = QuotationVersion::query()->findOrFail($quotation->active_version_id);

    expect(fn () => app(SendQuotationVersion::class)(
        $quotation,
        $version,
        SendQuotationVersionData::from([]),
    ))->toThrow(ValidationException::class);

    expect($quotation->refresh()->status)->toBe(QuotationStatus::Draft);
});

it('sends and declines an active version with replay-stable timestamps', function (): void {
    ['quotation' => $quotation, 'version' => $version, 'customer' => $customer] = createQuotationForLifecycle();

    app(SendQuotationVersion::class)($quotation, $version, SendQuotationVersionData::from([
        'sent_to' => $customer->id,
        'sent_via' => 'email',
    ]));

    expect($version->refresh()->status)->toBe(QuotationVersionStatus::Sent)
        ->and($version->sent_to)->toBe($customer->id)
        ->and($version->sent_via)->toBe('email')
        ->and($quotation->refresh()->status)->toBe(QuotationStatus::Sent);

    app(DeclineQuotationVersion::class)($quotation, $version, DeclineQuotationVersionData::from([
        'reason' => 'Budget changed',
    ]));

    expect($version->refresh()->status)->toBe(QuotationVersionStatus::Declined)
        ->and($version->decline_reason)->toBe('Budget changed')
        ->and($quotation->refresh()->status)->toBe(QuotationStatus::Declined)
        ->and($quotation->active_version_id)->toBeNull();

    $quotation->forceDelete();
    Verbs::replay();

    expect(Quotation::query()->findOrFail($quotation->id)->status)->toBe(QuotationStatus::Declined)
        ->and(Quotation::query()->findOrFail($quotation->id)->active_version_id)->toBeNull()
        ->and(QuotationVersion::query()->findOrFail($version->id)->decline_reason)->toBe('Budget changed');
});

it('requires another live version to be activated before declining the active one', function (): void {
    ['quotation' => $quotation, 'version' => $original] = createQuotationForLifecycle();
    $alternativeData = app(CreateQuotationVersion::class)($quotation, CreateQuotationVersionData::from([
        'version_type' => QuotationVersionType::Alternative->value,
    ]));
    $alternative = QuotationVersion::query()->findOrFail($alternativeData->id);

    expect(fn () => app(DeclineQuotationVersion::class)(
        $quotation->refresh(),
        $alternative,
        DeclineQuotationVersionData::from([]),
    ))->toThrow(ValidationException::class);

    expect($alternative->refresh()->status)->toBe(QuotationVersionStatus::Draft)
        ->and($original->refresh()->status)->toBe(QuotationVersionStatus::Draft);
});

it('accepts a standalone quotation, creates one active Project, and is idempotent', function (): void {
    ['quotation' => $quotation, 'version' => $version, 'customer' => $customer] = createQuotationForLifecycle();

    $first = app(AcceptQuotationVersion::class)($quotation, $version, AcceptQuotationVersionData::from([
        'accepted_by' => $customer->id,
    ]));
    $eventCount = DB::table('verb_events')->count();
    $second = app(AcceptQuotationVersion::class)($quotation->refresh(), $version->refresh(), AcceptQuotationVersionData::from([
        'accepted_by' => $customer->id,
    ]));

    $project = Project::query()->findOrFail($first->project_id);

    expect($first->project_id)->toBe($second->project_id)
        ->and(Project::query()->count())->toBe(1)
        ->and(DB::table('verb_events')->count())->toBe($eventCount)
        ->and($project->status)->toBe(ProjectStatus::Active)
        ->and($project->member_id)->toBe($customer->id)
        ->and($quotation->refresh()->status)->toBe(QuotationStatus::Accepted)
        ->and($quotation->accepted_version_id)->toBe($version->id)
        ->and($version->refresh()->status)->toBe(QuotationVersionStatus::Accepted)
        ->and(Demand::query()->count())->toBe(0);
});

it('accepts a Project-first quotation without creating or mutating another Project', function (): void {
    $customer = Member::factory()->organisation()->create();
    $project = Project::factory()->create([
        'name' => 'Existing Project',
        'member_id' => $customer->id,
        'status' => ProjectStatus::Draft,
    ]);
    ['quotation' => $quotation, 'version' => $version] = createQuotationForLifecycle(project: $project);

    $accepted = app(AcceptQuotationVersion::class)($quotation, $version, AcceptQuotationVersionData::from([]));

    expect($accepted->project_id)->toBe($project->id)
        ->and(Project::query()->count())->toBe(1)
        ->and($project->refresh()->name)->toBe('Existing Project')
        ->and($project->status)->toBe(ProjectStatus::Draft);
});

it('supersedes every other open version when the active version is accepted', function (): void {
    ['quotation' => $quotation, 'version' => $original] = createQuotationForLifecycle();
    $alternativeData = app(CreateQuotationVersion::class)($quotation, CreateQuotationVersionData::from([
        'version_type' => QuotationVersionType::Alternative->value,
    ]));
    $alternative = QuotationVersion::query()->findOrFail($alternativeData->id);
    QuotationItem::factory()->create([
        'quotation_id' => $quotation->id,
        'version_id' => $alternative->id,
    ]);

    app(AcceptQuotationVersion::class)($quotation->refresh(), $alternative, AcceptQuotationVersionData::from([]));

    expect($alternative->refresh()->status)->toBe(QuotationVersionStatus::Accepted)
        ->and($original->refresh()->status)->toBe(QuotationVersionStatus::Superseded)
        ->and($original->superseded_by_version_id)->toBe($alternative->id);

    expect(fn () => app(AcceptQuotationVersion::class)(
        $quotation->refresh(),
        $original->refresh(),
        AcceptQuotationVersionData::from([]),
    ))->toThrow(ValidationException::class);

    expect(Project::query()->count())->toBe(1);
});

it('creates a Project on acceptance without requiring projects.create permission', function (): void {
    ['quotation' => $quotation, 'version' => $version] = createQuotationForLifecycle();
    $acceptor = User::factory()->create();
    $acceptor->givePermissionTo(['quotations.access', 'quotations.view', 'quotations.accept']);
    $this->actingAs($acceptor);

    $accepted = app(AcceptQuotationVersion::class)($quotation, $version, AcceptQuotationVersionData::from([]));

    expect(Project::query()->whereKey($accepted->project_id)->exists())->toBeTrue()
        ->and($acceptor->can('projects.create'))->toBeFalse();
});

it('replays sent and accepted lifecycle state without duplicating the Project', function (): void {
    ['quotation' => $quotation, 'version' => $version] = createQuotationForLifecycle();
    app(SendQuotationVersion::class)($quotation, $version, SendQuotationVersionData::from([]));
    $accepted = app(AcceptQuotationVersion::class)($quotation->refresh(), $version->refresh(), AcceptQuotationVersionData::from([]));
    $eventCount = DB::table('verb_events')->count();

    Quotation::query()->whereKey($quotation->id)->forceDelete();
    Verbs::replay();

    $replayed = Quotation::query()->with('acceptedVersion')->findOrFail($quotation->id);

    expect(DB::table('verb_events')->count())->toBe($eventCount)
        ->and(Project::query()->count())->toBe(1)
        ->and($replayed->project_id)->toBe($accepted->project_id)
        ->and($replayed->status)->toBe(QuotationStatus::Accepted)
        ->and($replayed->acceptedVersion?->status)->toBe(QuotationVersionStatus::Accepted);
});
