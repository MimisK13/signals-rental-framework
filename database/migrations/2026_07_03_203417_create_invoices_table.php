<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Read-optimised projection of the event-sourced invoice lifecycle.
 *
 * Money columns are INTEGER minor units. The PK is application-assigned via
 * SequenceAllocator and baked into the InvoiceGenerated event so replay
 * reproduces identical ids. Status is a config-driven string key (not a DB enum).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('state_id')->unique();

            $table->string('number', 50);
            $table->string('type', 20);
            $table->string('status', 30);
            $table->boolean('is_posting')->default(true);
            $table->boolean('is_locked')->default(false);

            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('opportunity_id')->nullable();
            $table->foreign('opportunity_id')->references('id')->on('opportunities')->nullOnDelete();

            $table->dateTime('issued_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('overdue_notified_at')->nullable();
            $table->dateTime('paid_at')->nullable();

            $table->integer('subtotal')->default(0);
            $table->integer('tax_total')->default(0);
            $table->integer('total')->default(0);
            $table->integer('amount_paid')->default(0);
            $table->integer('amount_outstanding')->default(0);
            $table->integer('credited_total')->default(0);

            $table->string('currency_code', 3);
            $table->string('exchange_rate', 32)->default('1');
            $table->boolean('prices_include_tax')->default(false);

            $table->text('notes')->nullable();
            $table->string('reference')->nullable();
            $table->jsonb('tag_list')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('number');
            $table->index('type');
            $table->index('status');
            $table->index('opportunity_id');
            $table->index(['store_id', 'type']);
            $table->unique(['store_id', 'type', 'number'], 'invoices_store_id_type_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
