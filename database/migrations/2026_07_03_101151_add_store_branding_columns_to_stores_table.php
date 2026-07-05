<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->string('brand_logo_path')->nullable()->after('tag_list');
            $table->string('brand_primary_colour', 7)->nullable()->after('brand_logo_path');
            $table->string('brand_secondary_colour', 7)->nullable()->after('brand_primary_colour');
            $table->string('brand_font_family')->nullable()->after('brand_secondary_colour');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->dropColumn([
                'brand_logo_path',
                'brand_primary_colour',
                'brand_secondary_colour',
                'brand_font_family',
            ]);
        });
    }
};
