<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('organisation_tax_class_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_tax_class_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('tax_rate_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('seller_zone_id')->nullable();
            $table->unsignedBigInteger('buyer_zone_id')->nullable();
            $table->integer('priority')->default(0);
            $table->string('buyer_type', 16)->nullable();
            $table->boolean('has_tax_id')->nullable();
            $table->unsignedBigInteger('tax_category_id')->nullable();
            $table->string('result_type', 32)->nullable();
            $table->text('legal_text')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['organisation_tax_class_id', 'product_tax_class_id', 'tax_rate_id'],
                'tax_rules_class_rate_unique'
            );
            $table->index(['seller_zone_id', 'buyer_zone_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rules');
    }
};
