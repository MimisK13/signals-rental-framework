<?php

use App\Support\Database\StoreNumberUniqueConstraintPreflight;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'invoices',
        'credit_notes',
        'purchase_orders',
        'virtual_stock_intakes',
        'opportunities',
    ];

    public function up(): void
    {
        $this->addStoreIdToCreditNotes();

        $preflight = app(StoreNumberUniqueConstraintPreflight::class);

        foreach (self::TABLES as $table) {
            $preflight->assertNoDuplicates($table);

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                if ($table === 'invoices') {
                    $blueprint->unique(['store_id', 'type', 'number'], 'invoices_store_id_type_number_unique');

                    return;
                }

                $blueprint->unique(['store_id', 'number'], "{$table}_store_id_number_unique");
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                if ($table === 'invoices') {
                    $blueprint->dropUnique('invoices_store_id_type_number_unique');

                    return;
                }

                $blueprint->dropUnique("{$table}_store_id_number_unique");
            });
        }

        if (Schema::hasColumn('credit_notes', 'store_id')) {
            Schema::table('credit_notes', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('store_id');
            });
        }
    }

    private function addStoreIdToCreditNotes(): void
    {
        if (Schema::hasColumn('credit_notes', 'store_id')) {
            return;
        }

        Schema::table('credit_notes', function (Blueprint $table): void {
            $table->foreignId('store_id')->nullable()->after('invoice_id')->constrained('stores')->cascadeOnDelete();
        });

        $rows = DB::table('credit_notes')
            ->join('invoices', 'credit_notes.invoice_id', '=', 'invoices.id')
            ->select(['credit_notes.id', 'invoices.store_id'])
            ->get();

        foreach ($rows as $row) {
            DB::table('credit_notes')
                ->where('id', $row->id)
                ->update(['store_id' => $row->store_id]);
        }
    }
};
