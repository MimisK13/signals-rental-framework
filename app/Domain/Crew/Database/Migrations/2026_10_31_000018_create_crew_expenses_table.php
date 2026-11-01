<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const AMOUNT_NON_NEGATIVE_CHECK = 'crew_expenses_amount_non_negative_check';

    public function up(): void
    {
        Schema::create('crew_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('crew_member_id')->constrained('crew_members')->restrictOnDelete();
            $table->foreignId('crew_assignment_id')->nullable()->constrained('crew_assignments')->nullOnDelete();
            $table->string('category', 24);
            $table->string('status', 20)->default('draft');
            $table->date('incurred_on');
            $table->string('description', 255);
            $table->bigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->decimal('exchange_rate', 16, 6)->default(1);
            $table->bigInteger('amount_base_minor');
            $table->boolean('billable_to_client')->default(false);
            $table->decimal('markup_percent', 5, 2)->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejected_reason')->nullable();
            $table->dateTime('reimbursed_at')->nullable();
            $table->string('finance_reference', 80)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['crew_member_id', 'status']);
            $table->index(['project_id', 'status']);
            $table->index(['store_id', 'incurred_on']);
        });

        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement(sprintf(
                'ALTER TABLE crew_expenses ADD CONSTRAINT %s CHECK (amount_minor >= 0)',
                self::AMOUNT_NON_NEGATIVE_CHECK,
            ));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_expenses');
    }
};
