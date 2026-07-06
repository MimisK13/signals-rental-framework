<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_rules', function (Blueprint $table): void {
            $table->dropUnique('tax_rules_class_rate_unique');

            $table->foreignId('organisation_tax_class_id')->nullable()->change();
            $table->foreignId('product_tax_class_id')->nullable()->change();
            $table->foreignId('tax_rate_id')->nullable()->change();

            $table->string('name')->nullable()->after('id');
            $table->text('description')->nullable()->after('name');
            $table->foreignId('seller_zone_id')->nullable()->after('tax_rate_id')->constrained('tax_zones')->nullOnDelete();
            $table->foreignId('buyer_zone_id')->nullable()->after('seller_zone_id')->constrained('tax_zones')->nullOnDelete();
            $table->string('buyer_type', 8)->nullable()->after('buyer_zone_id');
            $table->boolean('has_tax_id')->nullable()->after('buyer_type');
            $table->foreignId('tax_category_id')->nullable()->after('has_tax_id')->constrained('tax_categories')->nullOnDelete();
            $table->string('result_type', 32)->nullable()->after('tax_category_id');
            $table->text('legal_text')->nullable()->after('result_type');

            $table->index(['seller_zone_id', 'buyer_zone_id', 'is_active']);
            $table->unique(
                ['organisation_tax_class_id', 'product_tax_class_id', 'tax_rate_id'],
                'tax_rules_class_rate_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('tax_rules', function (Blueprint $table): void {
            $table->dropIndex(['seller_zone_id', 'buyer_zone_id', 'is_active']);
            $table->dropUnique('tax_rules_class_rate_unique');

            $table->dropConstrainedForeignId('tax_category_id');
            $table->dropConstrainedForeignId('buyer_zone_id');
            $table->dropConstrainedForeignId('seller_zone_id');
            $table->dropColumn([
                'name',
                'description',
                'buyer_type',
                'has_tax_id',
                'result_type',
                'legal_text',
            ]);

            $table->foreignId('organisation_tax_class_id')->nullable(false)->change();
            $table->foreignId('product_tax_class_id')->nullable(false)->change();
            $table->foreignId('tax_rate_id')->nullable(false)->change();

            $table->unique(
                ['organisation_tax_class_id', 'product_tax_class_id', 'tax_rate_id'],
                'tax_rules_class_rate_unique',
            );
        });
    }
};
