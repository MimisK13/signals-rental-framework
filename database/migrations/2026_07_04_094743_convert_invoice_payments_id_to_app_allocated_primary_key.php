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

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildSqliteTable(withAutoIncrement: false);

            return;
        }

        DB::statement('ALTER TABLE invoice_payments ALTER COLUMN id DROP DEFAULT');
        DB::statement('DROP SEQUENCE IF EXISTS invoice_payments_id_seq');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
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
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists(self::TEMP_TABLE);

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

            $table->index('invoice_id');
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

        Schema::enableForeignKeyConstraints();
    }
};
