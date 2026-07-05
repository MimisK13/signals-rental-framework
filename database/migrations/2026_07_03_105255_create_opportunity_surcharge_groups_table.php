<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_surcharge_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opportunity_id')
                ->constrained('opportunities')
                ->cascadeOnDelete();
            $table->foreignId('surcharge_group_id')
                ->constrained('surcharge_groups')
                ->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['opportunity_id', 'surcharge_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_surcharge_groups');
    }
};
