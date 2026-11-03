<?php

use App\Domain\Invoices\Enums\InvoiceType;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Members\Models\Member;
use App\Domain\Numbering\Services\DocumentSequenceDefaults;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\PurchaseOrders\Models\PurchaseOrder;
use App\Domain\Stores\Models\Store;
use App\Support\Database\StoreNumberUniqueConstraintPreflight;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesIsolatedSqliteMigrations;

uses(UsesIsolatedSqliteMigrations::class);

describe('numbering scheme migrations (sqlite)', function () {
    it('includes the entity-number marker in the consolidated document_sequences schema', function () {
        expect(Schema::hasColumn('document_sequences', 'is_entity_number'))->toBeTrue();
    });

    it('classifies entity-number schemes when provisioning consolidated rows', function () {
        expect(DocumentSequenceDefaults::attributes('invoice_standard')['is_entity_number'])->toBeTrue()
            ->and(DocumentSequenceDefaults::attributes('quote')['is_entity_number'])->toBeFalse();
    });

    it('adds nullable indexed number columns to members and invoice_payments', function () {
        expect(Schema::hasColumn('members', 'number'))->toBeTrue()
            ->and(Schema::hasColumn('invoice_payments', 'number'))->toBeTrue();

        $member = Member::factory()->create(['number' => null]);
        expect($member->refresh()->number)->toBeNull();
    });

    it('blocks duplicate same-type invoice numbers after unique indexes are applied', function () {
        $store = Store::factory()->create();

        Invoice::factory()->create([
            'store_id' => $store->id,
            'type' => InvoiceType::Standard->value,
            'number' => 'INV-DUP-001',
        ]);

        expect(fn () => Invoice::factory()->create([
            'store_id' => $store->id,
            'type' => InvoiceType::Standard->value,
            'number' => 'INV-DUP-001',
        ]))->toThrow(QueryException::class);
    });

    it('allows cross-type invoice numbers in the same store after unique indexes are applied', function () {
        $store = Store::factory()->create();

        Invoice::factory()->create([
            'store_id' => $store->id,
            'type' => InvoiceType::Standard->value,
            'number' => 'INV-LEGACY-X',
        ]);

        Invoice::factory()->deposit()->create([
            'store_id' => $store->id,
            'number' => 'INV-LEGACY-X',
        ]);

        expect(Invoice::query()
            ->where('store_id', $store->id)
            ->where('number', 'INV-LEGACY-X')
            ->count())->toBe(2);
    });

    it('allows multiple null numbers per store on nullable number columns', function () {
        $store = Store::factory()->create();

        Opportunity::factory()->create([
            'store_id' => $store->id,
            'number' => null,
        ]);

        Opportunity::factory()->create([
            'store_id' => $store->id,
            'number' => null,
        ]);

        expect(Opportunity::query()->where('store_id', $store->id)->whereNull('number')->count())->toBe(2);
    });

    it('detects duplicate same-type invoice numbers before adding unique indexes', function () {
        DB::statement('DROP INDEX invoices_store_id_type_number_unique');

        $store = Store::factory()->create();

        Invoice::factory()->create([
            'store_id' => $store->id,
            'type' => InvoiceType::Standard->value,
            'number' => 'PREFLIGHT-DUP',
        ]);

        Invoice::factory()->create([
            'store_id' => $store->id,
            'type' => InvoiceType::Standard->value,
            'number' => 'PREFLIGHT-DUP',
        ]);

        $preflight = app(StoreNumberUniqueConstraintPreflight::class);

        expect(fn () => $preflight->assertNoDuplicates('invoices'))
            ->toThrow(RuntimeException::class, 'duplicate numbers exist: PREFLIGHT-DUP');

    });

    it('allows legacy cross-type invoice number overlaps during preflight', function () {
        $store = Store::factory()->create();

        Invoice::factory()->create([
            'store_id' => $store->id,
            'type' => InvoiceType::Standard->value,
            'number' => 'INV-2026-0001',
        ]);

        Invoice::factory()->deposit()->create([
            'store_id' => $store->id,
            'number' => 'INV-2026-0001',
        ]);

        Invoice::factory()->proforma()->create([
            'store_id' => $store->id,
            'number' => 'INV-2026-0001',
        ]);

        expect(fn () => app(StoreNumberUniqueConstraintPreflight::class)->assertNoDuplicates('invoices'))
            ->not->toThrow(RuntimeException::class);
    });

    it('assertNoDuplicates returns cleanly for non-invoice tables without duplicates', function () {
        $preflight = app(StoreNumberUniqueConstraintPreflight::class);

        expect(fn () => $preflight->assertNoDuplicates('purchase_orders'))->not->toThrow(RuntimeException::class);
    });

    it('throws with the store_id number constraint message for duplicate purchase order numbers', function () {
        DB::statement('DROP INDEX purchase_orders_store_id_number_unique');

        $store = Store::factory()->create();

        PurchaseOrder::factory()->create([
            'store_id' => $store->id,
            'number' => 'PO-DUP-1',
        ]);
        PurchaseOrder::factory()->create([
            'store_id' => $store->id,
            'number' => 'PO-DUP-1',
        ]);

        expect(fn () => app(StoreNumberUniqueConstraintPreflight::class)->assertNoDuplicates('purchase_orders'))
            ->toThrow(RuntimeException::class, '(store_id, number)');
    });

    it('contains all consolidated numbering columns', function () {
        expect(Schema::hasColumn('document_sequences', 'is_entity_number'))->toBeTrue()
            ->and(Schema::hasColumn('members', 'number'))->toBeTrue()
            ->and(Schema::hasColumn('invoice_payments', 'number'))->toBeTrue()
            ->and(Schema::hasColumn('credit_notes', 'store_id'))->toBeTrue();
    });
});
