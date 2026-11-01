<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERIOD_ORDERED_CHECK = 'crew_timesheets_period_ordered_check';

    public function up(): void
    {
        Schema::create('crew_timesheets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('crew_assignment_id')->constrained('crew_assignments')->restrictOnDelete();
            $table->foreignId('crew_member_id')->constrained('crew_members')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('draft');
            $table->integer('total_minutes')->default(0);
            $table->bigInteger('total_pay_minor')->default(0);
            $table->char('currency_code', 3);
            $table->string('rounding_rule', 20)->nullable();
            $table->jsonb('overtime_breakdown')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->dateTime('exported_to_finance_at')->nullable();
            $table->string('finance_reference', 80)->nullable();
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['crew_assignment_id', 'period_start', 'period_end']);
            $table->index(['crew_member_id', 'status']);
            $table->index(['project_id', 'status']);
            $table->index(['store_id', 'status']);
            $table->index(['status', 'period_end']);
        });

        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement(sprintf(
                'ALTER TABLE crew_timesheets ADD CONSTRAINT %s CHECK (period_end >= period_start)',
                self::PERIOD_ORDERED_CHECK,
            ));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_timesheets');
    }
};
