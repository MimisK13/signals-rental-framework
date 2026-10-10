<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('container_bulk_membership_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_level_id')->constrained('stock_levels')->restrictOnDelete();
            $table->foreignId('source_container_id')->nullable()->constrained('containers')->nullOnDelete();
            $table->foreignId('destination_container_id')->nullable()->constrained('containers')->nullOnDelete();
            $table->string('event_type', 30);
            $table->decimal('quantity', 10, 2);
            $table->timestampTz('occurred_at');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->decimal('source_quantity_before', 10, 2)->nullable();
            $table->decimal('source_quantity_after', 10, 2)->nullable();
            $table->decimal('destination_quantity_before', 10, 2)->nullable();
            $table->decimal('destination_quantity_after', 10, 2)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['stock_level_id', 'occurred_at'], 'idx_container_bulk_events_stock_occurred');
            $table->index(['source_container_id', 'occurred_at'], 'idx_container_bulk_events_source_occurred');
            $table->index(['destination_container_id', 'occurred_at'], 'idx_container_bulk_events_destination_occurred');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE container_bulk_membership_events
                  ADD CONSTRAINT chk_container_bulk_membership_events_quantity_positive
                  CHECK (quantity > 0)
            SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('container_bulk_membership_events');
    }
};
