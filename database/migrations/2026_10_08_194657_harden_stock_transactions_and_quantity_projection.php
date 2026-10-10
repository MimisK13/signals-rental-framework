<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_transactions', function (Blueprint $table): void {
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('quantity_before', 10, 2)->nullable();
            $table->decimal('quantity_after', 10, 2)->nullable();
            $table->foreignId('reversal_of_id')->nullable()
                ->constrained('stock_transactions')
                ->restrictOnDelete();

            $table->index('reversal_of_id');
            $table->unique('reversal_of_id', 'stock_transactions_one_reversal_unique');
            $table->index(['stock_level_id', 'transaction_at']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                'ALTER TABLE stock_levels ADD CONSTRAINT stock_levels_quantity_held_non_negative CHECK (quantity_held >= 0)',
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                'ALTER TABLE stock_levels DROP CONSTRAINT IF EXISTS stock_levels_quantity_held_non_negative',
            );
        }

        Schema::table('stock_transactions', function (Blueprint $table): void {
            $table->dropUnique('stock_transactions_one_reversal_unique');
            $table->dropIndex(['stock_level_id', 'transaction_at']);
            $table->dropIndex(['reversal_of_id']);
            $table->dropConstrainedForeignId('reversal_of_id');
            $table->dropColumn(['actor_user_id', 'quantity_before', 'quantity_after']);
        });
    }
};
