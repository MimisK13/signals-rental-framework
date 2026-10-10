<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets');
            $table->string('expense_type', 30);
            $table->integer('amount');
            $table->string('currency_code', 3);
            $table->string('exchange_rate', 32);
            $table->string('base_currency_code', 3);
            $table->integer('base_amount');
            $table->dateTime('occurred_at');
            $table->foreignId('supplier_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('reference')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'occurred_at']);
            $table->index('expense_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_expenses');
    }
};
