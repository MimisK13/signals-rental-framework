<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const RATE_NON_NEGATIVE_CHECK = 'crew_assignments_rate_non_negative_check';

    public function up(): void
    {
        Schema::create('crew_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('crew_request_id')->constrained('crew_requests')->cascadeOnDelete();
            $table->foreignId('crew_request_line_id')->constrained('crew_request_lines')->cascadeOnDelete();
            $table->foreignId('crew_member_id')->constrained('crew_members')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('status', 24)->default('proposed');
            $table->string('active_assignment_key', 120)->nullable();
            $table->foreignId('rate_card_id')->nullable()->constrained('crew_rate_cards')->nullOnDelete();
            $table->string('rate_type', 16)->default('hourly');
            $table->bigInteger('rate_amount_minor');
            $table->char('currency_code', 3);
            $table->decimal('exchange_rate_to_base', 16, 6)->default(1);
            $table->decimal('overtime_multiplier', 4, 2)->default(1.50);
            $table->decimal('min_call_hours', 4, 2)->nullable();
            $table->decimal('daily_call_hours', 4, 2)->nullable();
            $table->jsonb('allowances_snapshot')->nullable();
            $table->bigInteger('cost_estimate_minor')->nullable();
            $table->jsonb('cost_estimate_breakdown')->nullable();
            $table->dateTime('agreed_at')->nullable();
            $table->foreignId('agreed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('invited_at')->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('substitute_for_assignment_id')
                ->nullable()
                ->constrained('crew_assignments')
                ->nullOnDelete();
            $table->boolean('requires_realignment')->default(false);
            $table->dateTime('realignment_flagged_at')->nullable();
            $table->text('realignment_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('active_assignment_key');
            $table->index(['crew_request_id', 'status']);
            $table->index(['crew_request_line_id', 'status']);
            $table->index(['crew_member_id', 'status']);
            $table->index(['project_id', 'status']);
            $table->index(['store_id', 'confirmed_at']);
            $table->index('substitute_for_assignment_id');
            $table->index(['project_id', 'requires_realignment']);
        });

        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement(sprintf(
                'ALTER TABLE crew_assignments ADD CONSTRAINT %s CHECK (rate_amount_minor >= 0)',
                self::RATE_NON_NEGATIVE_CHECK,
            ));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_assignments');
    }
};
