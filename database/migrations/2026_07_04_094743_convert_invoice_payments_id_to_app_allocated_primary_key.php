<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Align invoice_payments.id with the other event-sourced invoice projection tables.
 *
 * The create migration accidentally used $table->id() (DB auto-increment) while
 * invoices, invoice_items, and invoice_taxes all use application-assigned PKs via
 * SequenceAllocator. Existing row ids are preserved verbatim; only the serial/
 * autoincrement machinery is removed so explicit-id inserts replay correctly.
 */
return new class extends Migration
{
    private const TEMP_TABLE = 'invoice_payments__app_pk';

    /** @var list<string> */
    private const LEGACY_INDEX_NAMES = [
        self::TEMP_TABLE.'_invoice_id_index',
        self::TEMP_TABLE.'_paid_at_index',
    ];

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            if (! $this->sqliteInvoicePaymentsUsesAutoIncrement()) {
                $this->normalizeSqliteInvoicePaymentIndexNames();

                return;
            }

            $this->rebuildSqliteTable(withAutoIncrement: false);

            return;
        }

        DB::statement('ALTER TABLE invoice_payments ALTER COLUMN id DROP DEFAULT');
        DB::statement('DROP SEQUENCE IF EXISTS invoice_payments_id_seq');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            if ($this->sqliteInvoicePaymentsUsesAutoIncrement()) {
                return;
            }

            $this->rebuildSqliteTable(withAutoIncrement: true);

            return;
        }

        DB::statement('CREATE SEQUENCE invoice_payments_id_seq');

        $nextValue = (int) (DB::table('invoice_payments')->max('id') ?? 0) + 1;
        DB::statement("SELECT setval('invoice_payments_id_seq', {$nextValue}, false)");

        DB::statement("ALTER TABLE invoice_payments ALTER COLUMN id SET DEFAULT nextval('invoice_payments_id_seq')");
        DB::statement('ALTER SEQUENCE invoice_payments_id_seq OWNED BY invoice_payments.id');
    }

    private function rebuildSqliteTable(bool $withAutoIncrement): void
    {
        $this->dropSqliteConversionArtifacts();

        Schema::disableForeignKeyConstraints();

        Schema::create(self::TEMP_TABLE, function (Blueprint $table) use ($withAutoIncrement): void {
            if ($withAutoIncrement) {
                $table->id();
            } else {
                $table->unsignedBigInteger('id')->primary();
            }

            $table->unsignedBigInteger('invoice_id');
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();

            $table->integer('amount');
            $table->string('payment_method', 50);
            $table->string('reference')->nullable();
            $table->dateTime('paid_at');
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('paid_at');
        });

        DB::statement(<<<'SQL'
            INSERT INTO invoice_payments__app_pk (
                id,
                invoice_id,
                amount,
                payment_method,
                reference,
                paid_at,
                notes,
                created_by,
                created_at,
                updated_at
            )
            SELECT
                id,
                invoice_id,
                amount,
                payment_method,
                reference,
                paid_at,
                notes,
                created_by,
                created_at,
                updated_at
            FROM invoice_payments
            SQL);

        Schema::drop('invoice_payments');
        Schema::rename(self::TEMP_TABLE, 'invoice_payments');

        $this->normalizeSqliteInvoicePaymentIndexNames();

        Schema::enableForeignKeyConstraints();
    }

    private function dropSqliteConversionArtifacts(): void
    {
        Schema::dropIfExists(self::TEMP_TABLE);

        foreach (self::LEGACY_INDEX_NAMES as $indexName) {
            DB::statement('DROP INDEX IF EXISTS "'.$indexName.'"');
        }
    }

    private function normalizeSqliteInvoicePaymentIndexNames(): void
    {
        if (! Schema::hasTable('invoice_payments')) {
            return;
        }

        $targetIndexes = [
            'invoice_payments_invoice_id_index' => 'invoice_id',
            'invoice_payments_paid_at_index' => 'paid_at',
        ];

        foreach (self::LEGACY_INDEX_NAMES as $legacyIndex) {
            DB::statement('DROP INDEX IF EXISTS "'.$legacyIndex.'"');
        }

        foreach ($targetIndexes as $indexName => $column) {
            $exists = DB::scalar(
                'SELECT 1 FROM sqlite_master WHERE type = ? AND name = ? LIMIT 1',
                ['index', $indexName],
            );

            if ($exists === null) {
                DB::statement(sprintf(
                    'CREATE INDEX "%s" ON "invoice_payments" ("%s")',
                    $indexName,
                    $column,
                ));
            }
        }
    }

    private function sqliteInvoicePaymentsUsesAutoIncrement(): bool
    {
        if (! Schema::hasTable('invoice_payments')) {
            return false;
        }

        $ddl = DB::scalar(
            "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'invoice_payments'",
        );

        if (! is_string($ddl)) {
            return false;
        }

        return str_contains(strtoupper($ddl), 'AUTOINCREMENT');
    }
};
