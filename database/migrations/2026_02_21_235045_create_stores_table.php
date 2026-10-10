<?php

use App\Enums\ShortageDispatchPolicy;
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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('county')->nullable();
            $table->string('postcode', 20)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('shortage_policy', 16)->default('warn');
            $table->string('shortage_dispatch_policy', 16)->default(ShortageDispatchPolicy::default()->value);
            $table->string('timezone', 64)->nullable();
            $table->jsonb('operating_hours')->nullable();
            $table->boolean('is_virtual')->default(false);
            $table->boolean('include_in_default_queries')->default(true);
            $table->boolean('shortage_auto_resolve_enabled')->default(false);
            $table->jsonb('shortage_preferred_resolvers')->nullable();
            $table->jsonb('tag_list')->nullable();
            $table->string('brand_logo_path')->nullable();
            $table->string('brand_primary_colour', 7)->nullable();
            $table->string('brand_secondary_colour', 7)->nullable();
            $table->string('brand_font_family')->nullable();
            $table->unsignedBigInteger('tax_zone_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
