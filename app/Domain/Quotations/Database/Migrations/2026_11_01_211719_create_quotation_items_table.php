<?php

declare(strict_types=1);

use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Rates\Models\RateDefinition;
use App\Models\RevenueGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('state_id')->unique();
            $table->foreignIdFor(Quotation::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(QuotationVersion::class, 'version_id')->constrained('quotation_versions')->cascadeOnDelete();
            $table->nullableMorphs('itemable');
            $table->string('item_type', 32)->default('product');
            $table->string('path')->default('');
            $table->foreignIdFor(RevenueGroup::class)->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('quantity', 10, 2)->default(0);
            $table->bigInteger('unit_price')->default(0);
            $table->foreignIdFor(RateDefinition::class)->nullable()->constrained()->nullOnDelete();
            $table->smallInteger('charge_period')->default(1);
            $table->bigInteger('total')->default(0);
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('gross_total')->default(0);
            $table->string('currency_code', 3)->nullable();
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->boolean('is_discount_manual')->default(false);
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->smallInteger('transaction_type')->default(0);
            $table->dateTimeTz('starts_at')->nullable();
            $table->dateTimeTz('ends_at')->nullable();
            $table->boolean('is_optional')->default(false);
            $table->jsonb('custom_fields')->nullable();
            $table->jsonb('pricing_breakdown')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['quotation_id', 'path']);
            $table->index(['version_id', 'path']);
            $table->index('transaction_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
    }
};
