<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_lifecycle_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('event_type', 30);
            $table->timestamp('occurred_at');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actor_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('previous_status', 20)->nullable();
            $table->string('new_status', 20);
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('location')->nullable();
            $table->string('disposition', 20)->nullable();
            $table->integer('proceeds_amount')->nullable();
            $table->string('proceeds_currency_code', 3)->nullable();
            $table->foreignId('buyer_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'occurred_at']);
            $table->index(['asset_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_lifecycle_events');
    }
};
