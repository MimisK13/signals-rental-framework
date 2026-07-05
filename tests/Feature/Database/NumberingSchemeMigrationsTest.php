<?php

use App\Enums\InvoiceType;
use App\Models\CreditNote;
use App\Models\DocumentSequence;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Store;
use App\Services\SequenceAllocator;
use App\Support\Database\StoreNumberUniqueConstraintPreflight;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesIsolatedSqliteMigrations;

uses(UsesIsolatedSqliteMigrations::class);

/**
 * Numbering scheme migrations in reverse rollback order (last migrated first).
 *
 * @return list<string>
 */
function numberingSchemeMigrationPaths(): array
{
    return [
        'database/migrations/2026_07_04_185942_add_store_number_unique_indexes_to_numbered_entities_table.php',
        'database/migrations/2026_07_04_185941_add_number_columns_to_members_and_invoice_payments_table.php',
        'database/migrations/2026_07_04_185940_add_is_entity_number_to_document_sequences_table.php',
    ];
}

function rollbackSingleMigration(string $path): void
{
    Artisan::call('migrate:rollback', [
        '--path' => $path,
        '--step' => 1,
        '--force' => true,
    ]);
}

describe('numbering scheme migrations (sqlite)', function () {
    it('adds is_entity_number to document_sequences and backfills entity types', function () {
        expect(Schema::hasColumn('document_sequences', 'is_entity_number'))->toBeTrue();
    });

    it('backfills is_entity_number for entity document types when migration runs', function () {
        foreach (numberingSchemeMigrationPaths() as $path) {
            rollbackSingleMigration($path);
        }

        $store = Store::factory()->create();

        DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => 'invoice_standard',
        ]);

        DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => 'quote',
        ]);

        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_07_04_185940_add_is_entity_number_to_document_sequences_table.php',
            '--force' => true,
        ]);

        expect(DocumentSequence::query()->where('document_type', 'invoice_standard')->value('is_entity_number'))->toBeTruthy()
            ->and(DocumentSequence::query()->where('document_type', 'quote')->value('is_entity_number'))->toBeFalsy();
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
        rollbackSingleMigration('database/migrations/2026_07_04_185942_add_store_number_unique_indexes_to_numbered_entities_table.php');

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

        Invoice::query()
            ->where('store_id', $store->id)
            ->where('number', 'PREFLIGHT-DUP')
            ->forceDelete();

        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_07_04_185942_add_store_number_unique_indexes_to_numbered_entities_table.php',
            '--force' => true,
        ]);
    });

    it('allows legacy cross-type invoice number overlaps during preflight', function () {
        rollbackSingleMigration('database/migrations/2026_07_04_185942_add_store_number_unique_indexes_to_numbered_entities_table.php');

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

        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_07_04_185942_add_store_number_unique_indexes_to_numbered_entities_table.php',
            '--force' => true,
        ]);
    });

    it('backfills credit note store_id from invoice when unique indexes are applied', function () {
        rollbackSingleMigration('database/migrations/2026_07_04_185942_add_store_number_unique_indexes_to_numbered_entities_table.php');

        $invoice = Invoice::factory()->create();
        $creditNote = CreditNote::query()->create([
            'id' => app(SequenceAllocator::class)->next('credit_notes'),
            'invoice_id' => $invoice->id,
            'number' => 'CN-BACKFILL-TEST',
            'status' => 'issued',
            'subtotal' => -1000,
            'tax_total' => -200,
            'total' => -1200,
            'currency_code' => 'GBP',
            'exchange_rate' => '1',
            'prices_include_tax' => false,
            'issued_at' => now(),
        ]);

        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_07_04_185942_add_store_number_unique_indexes_to_numbered_entities_table.php',
            '--force' => true,
        ]);

        expect(DB::table('credit_notes')->where('id', $creditNote->id)->value('store_id'))->toBe($invoice->store_id);
    });

    it('rolls back and re-applies numbering scheme migrations cleanly', function () {
        foreach (numberingSchemeMigrationPaths() as $path) {
            rollbackSingleMigration($path);
        }

        expect(Schema::hasColumn('document_sequences', 'is_entity_number'))->toBeFalse()
            ->and(Schema::hasColumn('members', 'number'))->toBeFalse()
            ->and(Schema::hasColumn('credit_notes', 'store_id'))->toBeFalse();

        foreach (array_reverse(numberingSchemeMigrationPaths()) as $path) {
            Artisan::call('migrate', [
                '--path' => $path,
                '--force' => true,
            ]);
        }

        expect(Schema::hasColumn('document_sequences', 'is_entity_number'))->toBeTrue()
            ->and(Schema::hasColumn('members', 'number'))->toBeTrue();
    });
});
