<?php

use App\Actions\Invoices\CreateDraftInvoice;
use App\Actions\Invoices\DeleteDraftInvoice;
use App\Data\Invoices\CreateDraftInvoiceData;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('keeps deleted drafts soft-deleted after projection wipe and replay', function () {
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $member = Member::factory()->organisation()->create();

    $draft = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'type' => InvoiceType::Standard->value,
    ]));

    $invoice = Invoice::query()->findOrFail($draft->id);

    (new DeleteDraftInvoice)($invoice);

    $deletedBefore = Invoice::withTrashed()->findOrFail($invoice->id);

    expect($deletedBefore->deleted_at)->not->toBeNull()
        ->and(Invoice::query()->find($invoice->id))->toBeNull();

    $eventCountBeforeReplay = verbEventCount();

    Invoice::withTrashed()->whereKey($invoice->id)->forceDelete();

    Verbs::replay();

    $replayed = Invoice::withTrashed()->find($invoice->id);

    expect(verbEventCount())->toBe($eventCountBeforeReplay)
        ->and($replayed)->not->toBeNull()
        ->and($replayed->deleted_at)->not->toBeNull()
        ->and(Invoice::query()->find($invoice->id))->toBeNull();

    Verbs::replay();

    expect(verbEventCount())->toBe($eventCountBeforeReplay)
        ->and(Invoice::withTrashed()->find($invoice->id)?->deleted_at)->not->toBeNull()
        ->and(Invoice::query()->find($invoice->id))->toBeNull();
});
