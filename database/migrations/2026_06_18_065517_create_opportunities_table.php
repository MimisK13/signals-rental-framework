<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The `opportunities` table is the read-optimised projection of the
 * event-sourced opportunity lifecycle. Columns are RMS-aligned (matching the
 * Current RMS schema) so list views, the API, and the availability engine query
 * the same shape they would in a non-event-sourced system. All mutations flow
 * through Verbs events whose handle() methods dual-write this table.
 *
 * Money columns are INTEGER minor units (pence/cents/fils). Dates are stored in
 * UTC. The two-axis state model lives in `state` (int) + `status` (int).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            // RMS-compatible small integer primary key. Unlike a DB
            // auto-increment column, this PK is application-assigned: the
            // CreateOpportunity action allocates it via SequenceAllocator and
            // bakes it into the OpportunityCreated event, so a truncate +
            // Verbs::replay() rebuild reproduces identical ids (replay-stable).
            $table->unsignedBigInteger('id')->primary();

            // Link to the Verbs event stream. The event-sourced state is keyed by
            // a snowflake; the projection keeps its own integer PK for RMS
            // compatibility, so this column bridges the two. Unique + indexed so
            // projections upsert by it and replay rebuilds deterministically.
            $table->unsignedBigInteger('state_id')->unique();

            // Identity / header
            $table->string('subject');
            $table->text('description')->nullable();
            $table->string('number')->nullable();
            $table->string('reference')->nullable();
            $table->text('external_description')->nullable();

            // Two-axis state model
            $table->smallInteger('state')->default(0);
            $table->smallInteger('status')->default(0);
            $table->unsignedBigInteger('active_version_id')->default(0);
            $table->integer('version_count')->default(0);
            $table->boolean('has_alternatives')->default(false);

            // Relationships (RMS field names). Nullable to keep the projection
            // self-contained and to tolerate partial header data on drafts.
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('owned_by')->nullable()->constrained('members')->nullOnDelete();

            // Hire / charge period (UTC)
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('charge_starts_at')->nullable();
            $table->dateTime('charge_ends_at')->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->decimal('exchange_rate', 20, 10)->default(1);
            $table->boolean('exchange_rate_locked')->default(false);
            $table->boolean('tax_locked')->default(false);

            // Calculated money totals — INTEGER minor units.
            $table->integer('charge_total')->default(0);
            $table->integer('rental_charge_total')->default(0);
            $table->integer('sale_charge_total')->default(0);
            $table->integer('service_charge_total')->default(0);
            $table->integer('sub_rental_charge_total')->default(0);
            $table->integer('transit_charge_total')->default(0);
            $table->integer('loss_damage_charge_total')->default(0);
            $table->integer('deal_total')->nullable();
            $table->string('deal_price_mode', 32)->default('flat_headline');
            $table->integer('deal_price_original_total')->nullable();
            $table->integer('charge_excluding_tax_total')->default(0);
            $table->integer('charge_including_tax_total')->default(0);
            $table->integer('tax_total')->default(0);

            // Flags
            $table->boolean('prices_include_tax')->default(false);
            $table->boolean('invoiced')->default(false);
            $table->boolean('has_shortage')->default(false)->index();
            $table->dateTime('prep_starts_at')->nullable();
            $table->dateTime('prep_ends_at')->nullable();
            $table->dateTime('load_starts_at')->nullable();
            $table->dateTime('load_ends_at')->nullable();
            $table->dateTime('deliver_starts_at')->nullable();
            $table->dateTime('deliver_ends_at')->nullable();
            $table->dateTime('setup_starts_at')->nullable();
            $table->dateTime('setup_ends_at')->nullable();
            $table->dateTime('show_starts_at')->nullable();
            $table->dateTime('show_ends_at')->nullable();
            $table->dateTime('takedown_starts_at')->nullable();
            $table->dateTime('takedown_ends_at')->nullable();
            $table->dateTime('collect_starts_at')->nullable();
            $table->dateTime('collect_ends_at')->nullable();
            $table->dateTime('unload_starts_at')->nullable();
            $table->dateTime('unload_ends_at')->nullable();
            $table->dateTime('deprep_starts_at')->nullable();
            $table->dateTime('deprep_ends_at')->nullable();
            $table->dateTime('ordered_at')->nullable();
            $table->dateTime('quote_invalid_at')->nullable();
            $table->boolean('use_chargeable_days')->default(false);
            $table->decimal('chargeable_days', 8, 1)->nullable();
            $table->boolean('open_ended_rental')->default(false);
            $table->boolean('customer_collecting')->default(false);
            $table->boolean('customer_returning')->default(false);
            $table->text('delivery_instructions')->nullable();
            $table->text('collection_instructions')->nullable();
            $table->unsignedBigInteger('source_opportunity_id')->nullable();
            $table->unsignedBigInteger('delivery_address_id')->nullable();
            $table->unsignedBigInteger('collection_address_id')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();

            // Flexible config
            $table->jsonb('tag_list')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('state');
            $table->index('status');
            $table->index('number');
            $table->index('reference');
            $table->index(['starts_at', 'ends_at']);
            $table->unique(['store_id', 'number'], 'opportunities_store_id_number_unique');
            $table->foreign('delivery_address_id')->references('id')->on('addresses')->nullOnDelete();
            $table->foreign('collection_address_id')->references('id')->on('addresses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
