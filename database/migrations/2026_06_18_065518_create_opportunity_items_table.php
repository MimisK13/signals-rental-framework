<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The `opportunity_items` table is the read-optimised projection of the
 * event-sourced opportunity line items. Columns are RMS-aligned (section 3.2 of
 * opportunity-lifecycle.md) so list views, the API, and the availability engine
 * query the same shape they would in a non-event-sourced system. All mutations
 * flow through Verbs item events (M3) whose handle() methods dual-write this row.
 *
 * Money columns (`unit_price`, `total`) are INTEGER minor units (pence/cents/fils)
 * matching the project-wide money convention and the OpportunityItemState's
 * integer-pence properties; the plan's decimal(10,2) is the RMS display shape,
 * stored here as minor units. `quantity`, `discount_percent`, and `tax_rate`
 * are genuine decimals. Line items are hard rows tied to the opportunity (no
 * soft delete — removal is event-sourced via ItemRemovedFromOpportunity in M3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_items', function (Blueprint $table) {
            // RMS-compatible small integer primary key. Application-assigned: the
            // ItemAddedToOpportunity event (M3) allocates it via SequenceAllocator
            // ('opportunity_items') and bakes it into the event, so a truncate +
            // Verbs::replay() rebuild reproduces identical ids (replay-stable).
            $table->unsignedBigInteger('id')->primary();

            // Bridge to the Verbs event stream (snowflake StateId). Unique +
            // indexed so projections upsert by it and replay rebuilds
            // deterministically — mirrors `opportunities.state_id`.
            $table->unsignedBigInteger('state_id')->unique();

            // Parent opportunity (the projection's small int id).
            $table->foreignId('opportunity_id')->constrained('opportunities')->cascadeOnDelete();

            // Quotation version scope (Signals-only; null for orders/legacy data).
            // The opportunity_versions table lands in M4 — kept as an unconstrained
            // nullable int here so M3 items can be added before versioning exists.
            // The FK constraint is added in
            // 2026_06_20_095000_add_version_id_foreign_key_to_opportunity_items_table.php
            // once opportunity_versions exists.
            $table->unsignedBigInteger('version_id')->nullable();

            // Catalogued item reference — polymorphic (products today, services and
            // other catalogue types later), so no single-table FK constraint.
            $table->unsignedBigInteger('itemable_id')->nullable();
            $table->string('itemable_type')->nullable();

            // Snapshot of the item identity at the time it was added.
            $table->string('name');
            $table->text('description')->nullable();

            // Quantity + pricing. unit_price/total are INTEGER minor units.
            $table->decimal('quantity', 10, 2)->default(0);
            $table->integer('unit_price')->default(0);
            $table->smallInteger('charge_period')->default(1);
            $table->integer('total')->default(0);
            $table->string('currency_code', 3)->nullable();
            $table->decimal('invoiced_quantity', 10, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->boolean('is_discount_manual')->default(false);
            $table->decimal('deal_price_discount_percent', 18, 10)->nullable();
            $table->integer('deal_price')->nullable();
            $table->integer('deal_price_original_total')->nullable();
            $table->integer('surcharge_total')->default(0);
            $table->decimal('tax_rate', 5, 2)->nullable();

            // Commercial nature of the line (rental/sale/service/sub-rental).
            $table->smallInteger('transaction_type')->default(0);

            // Per-item date overrides (UTC). When set, the item's charge window
            // differs from the opportunity's charge_starts_at / charge_ends_at.
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->foreignId('dispatch_store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('return_store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->decimal('dispatched_quantity', 10, 2)->default(0);
            $table->decimal('returned_quantity', 10, 2)->default(0);
            $table->dateTime('shortage_escalated_at')->nullable();
            $table->string('item_type', 255)->default('product');
            $table->string('path')->default('');
            $table->unsignedBigInteger('revenue_group_id')->nullable();
            $table->unsignedBigInteger('price_category_tier_id')->nullable();
            $table->decimal('price_category_tier_multiplier', 10, 4)->nullable();

            // Display ordering + flags.
            $table->boolean('is_optional')->default(false);

            // Flexible per-item config + freeform notes.
            $table->jsonb('custom_fields')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('opportunity_id');
            $table->index('version_id');
            $table->index(['itemable_type', 'itemable_id']);
            $table->index('transaction_type');
            $table->index(['opportunity_id', 'path']);
            $table->index('revenue_group_id');
            $table->foreign('revenue_group_id')->references('id')->on('revenue_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_items');
    }
};
