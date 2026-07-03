<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_zone_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tax_zone_id')->constrained()->cascadeOnDelete();
            $table->string('country_code', 2);
            $table->timestamps();

            $table->unique(['tax_zone_id', 'country_code']);
            $table->index('country_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_zone_members');
    }
};
