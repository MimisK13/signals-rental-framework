<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->foreignId('tax_zone_id')->nullable()->after('discount_category_id')->constrained('tax_zones')->nullOnDelete();
            $table->string('buyer_type', 8)->default('b2b')->after('tax_zone_id');
            $table->boolean('tax_exempt')->default(false)->after('buyer_type');
        });

        Schema::table('stores', function (Blueprint $table): void {
            $table->foreignId('tax_zone_id')->nullable()->after('country_id')->constrained('tax_zones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tax_zone_id');
        });

        Schema::table('members', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tax_zone_id');
            $table->dropColumn(['buyer_type', 'tax_exempt']);
        });
    }
};
