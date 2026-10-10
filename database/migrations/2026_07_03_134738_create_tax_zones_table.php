<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_zones', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug', 32)->unique();
            $table->text('description')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::table('members', function (Blueprint $table): void {
            $table->foreign('tax_zone_id')->references('id')->on('tax_zones')->nullOnDelete();
        });

        Schema::table('stores', function (Blueprint $table): void {
            $table->foreign('tax_zone_id')->references('id')->on('tax_zones')->nullOnDelete();
        });

        Schema::table('tax_rules', function (Blueprint $table): void {
            $table->foreign('seller_zone_id')->references('id')->on('tax_zones')->nullOnDelete();
            $table->foreign('buyer_zone_id')->references('id')->on('tax_zones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tax_rules', function (Blueprint $table): void {
            $table->dropForeign(['seller_zone_id']);
            $table->dropForeign(['buyer_zone_id']);
        });

        Schema::table('members', function (Blueprint $table): void {
            $table->dropForeign(['tax_zone_id']);
        });

        Schema::table('stores', function (Blueprint $table): void {
            $table->dropForeign(['tax_zone_id']);
        });

        Schema::dropIfExists('tax_zones');
    }
};
