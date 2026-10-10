<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_item_surcharges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_item_id')
                ->constrained('opportunity_items')
                ->cascadeOnDelete();
            $table->foreignId('surcharge_id')
                ->nullable()
                ->constrained('surcharges')
                ->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('surcharge_type', 16)->nullable();
            $table->decimal('percent_value', 5, 2)->nullable();
            $table->integer('fixed_value_minor')->nullable();
            $table->integer('amount');
            $table->foreignId('product_tax_class_id')
                ->nullable()
                ->constrained('product_tax_classes')
                ->nullOnDelete();
            $table->boolean('is_manual_override')->default(false);
            $table->boolean('is_disabled')->default(false);
            $table->timestamps();

            $table->unique(['opportunity_item_id', 'surcharge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_item_surcharges');
    }
};
