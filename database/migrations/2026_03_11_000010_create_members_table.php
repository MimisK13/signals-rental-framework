<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('membership_type');
            $table->boolean('is_active')->default(true);
            $table->boolean('bookable')->default(false);
            $table->smallInteger('location_type')->default(0);
            $table->integer('day_cost')->default(0);
            $table->integer('hour_cost')->default(0);
            $table->integer('distance_cost')->default(0);
            $table->integer('flat_rate_cost')->default(0);
            $table->unsignedBigInteger('lawful_basis_type_id')->nullable();
            $table->text('description')->nullable();
            $table->string('locale')->nullable();
            $table->string('default_currency_code', 3)->nullable();
            $table->foreignId('sale_tax_class_id')->nullable()->constrained('organisation_tax_classes')->nullOnDelete();
            $table->foreignId('purchase_tax_class_id')->nullable()->constrained('organisation_tax_classes')->nullOnDelete();
            $table->jsonb('tag_list')->nullable();
            $table->string('icon_url')->nullable();
            $table->string('icon_thumb_url')->nullable();
            $table->string('mapping_id')->nullable();
            $table->string('account_number')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('peppol_id')->nullable();
            $table->string('chamber_of_commerce_number')->nullable();
            $table->string('global_location_number')->nullable();
            $table->boolean('is_cash')->default(false);
            $table->boolean('is_on_stop')->default(false);
            $table->smallInteger('rating')->default(0);
            $table->unsignedBigInteger('owned_by')->nullable();
            $table->unsignedBigInteger('price_category_id')->nullable();
            $table->unsignedBigInteger('price_category_tier_id')->nullable();
            $table->unsignedBigInteger('discount_category_id')->nullable();
            $table->unsignedBigInteger('invoice_term_id')->nullable();
            $table->integer('invoice_term_length')->default(0);
            $table->string('title', 50)->nullable();
            $table->string('department')->nullable();
            $table->jsonb('communication_preferences')->nullable();
            $table->unsignedBigInteger('tax_zone_id')->nullable();
            $table->string('buyer_type', 16)->default('business');
            $table->boolean('tax_exempt')->default(false);
            $table->boolean('tax_number_validated')->nullable();
            $table->timestamp('tax_number_validated_at')->nullable();
            $table->jsonb('tax_number_validation')->nullable();
            $table->string('number', 50)->nullable()->index();
            $table->foreign('lawful_basis_type_id')->references('id')->on('list_values')->nullOnDelete();
            $table->foreign('owned_by')->references('id')->on('members')->nullOnDelete();
            $table->foreign('invoice_term_id')->references('id')->on('list_values')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('membership_type');
            $table->index('is_active');
            $table->index('name');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreign('member_id')->references('id')->on('members')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['member_id']);
        });

        Schema::dropIfExists('members');
    }
};
