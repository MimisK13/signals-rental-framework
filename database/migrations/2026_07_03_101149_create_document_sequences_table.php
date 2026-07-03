<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('document_type');
            $table->string('prefix')->default('');
            $table->string('suffix')->default('');
            $table->boolean('include_year')->default(true);
            $table->boolean('include_month')->default(false);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedSmallInteger('padding')->default(4);
            $table->string('reset_frequency')->default('never');
            $table->unsignedSmallInteger('current_period_year')->nullable();
            $table->unsignedTinyInteger('current_period_month')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
