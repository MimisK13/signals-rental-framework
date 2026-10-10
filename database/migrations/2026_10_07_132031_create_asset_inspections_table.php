<?php

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
        Schema::create('asset_inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspection_policy_id')->constrained('inspection_policies')->restrictOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->date('due_at');
            $table->dateTime('scheduled_start_at')->nullable();
            $table->dateTime('scheduled_end_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('result', 30)->nullable();
            $table->foreignId('inspector_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('inspector_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'inspection_policy_id', 'due_at']);
            $table->index(['asset_id', 'completed_at']);
            $table->index(['status', 'due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_inspections');
    }
};
