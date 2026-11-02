<?php

declare(strict_types=1);

use App\Domain\Quotations\Actions\CreateQuotation;
use App\Domain\Quotations\Data\CreateQuotationData;
use App\Domain\Quotations\Models\Quotation;
use App\Models\Member;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('rebuilds quotation and initial version projections from the canonical event stream', function (): void {
    $customer = Member::factory()->organisation()->create();
    $store = Store::factory()->create();

    $result = app(CreateQuotation::class)(CreateQuotationData::from([
        'subject' => 'Replayable quotation',
        'store_id' => $store->id,
        'member_id' => $customer->id,
        'starts_at' => '2027-10-01T08:00:00Z',
        'ends_at' => '2027-10-03T18:00:00Z',
    ]));

    $before = Quotation::query()->with('activeVersion')->findOrFail($result->id);
    $expected = [
        'quotation_id' => $before->id,
        'quotation_state_id' => $before->state_id,
        'version_id' => $before->active_version_id,
        'version_state_id' => $before->activeVersion?->state_id,
        'number' => $before->number,
        'subject' => $before->subject,
    ];
    $eventCount = DB::table('verb_events')->count();

    Quotation::query()->whereKey($before->id)->forceDelete();

    Verbs::replay();

    $after = Quotation::query()->with('activeVersion')->findOrFail($result->id);

    expect(DB::table('verb_events')->count())->toBe($eventCount)
        ->and($after->id)->toBe($expected['quotation_id'])
        ->and($after->state_id)->toBe($expected['quotation_state_id'])
        ->and($after->active_version_id)->toBe($expected['version_id'])
        ->and($after->activeVersion?->state_id)->toBe($expected['version_state_id'])
        ->and($after->number)->toBe($expected['number'])
        ->and($after->subject)->toBe($expected['subject'])
        ->and($after->versions()->count())->toBe(1);
});
