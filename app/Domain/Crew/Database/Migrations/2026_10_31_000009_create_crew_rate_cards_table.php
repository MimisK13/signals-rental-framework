<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const EFFECTIVE_DATES_CHECK = 'crew_rate_cards_effective_dates_check';

    private const RATE_PRESENT_CHECK = 'crew_rate_cards_rate_present_check';

    public function up(): void
    {
        Schema::create('crew_rate_cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('crew_member_id')->nullable()->constrained('crew_members')->cascadeOnDelete();
            $table->foreignId('crew_role_id')->nullable()->constrained('crew_roles')->nullOnDelete();
            $table->string('engagement_type', 24)->nullable();
            $table->char('currency_code', 3);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->bigInteger('hourly_rate_minor')->nullable();
            $table->bigInteger('daily_rate_minor')->nullable();
            $table->bigInteger('fixed_rate_minor')->nullable();
            $table->decimal('overtime_multiplier', 4, 2)->default(1.50);
            $table->decimal('weekend_multiplier', 4, 2)->nullable();
            $table->decimal('night_multiplier', 4, 2)->nullable();
            $table->decimal('holiday_multiplier', 4, 2)->nullable();
            $table->decimal('min_call_hours', 4, 2)->nullable();
            $table->decimal('daily_call_hours', 4, 2)->nullable();
            $table->bigInteger('travel_rate_minor')->nullable();
            $table->bigInteger('per_diem_minor')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['crew_member_id', 'effective_from', 'effective_to']);
            $table->index(['crew_role_id', 'effective_from', 'effective_to']);
            $table->index(['store_id', 'effective_from', 'effective_to']);
            $table->index('is_active');
        });

        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement(sprintf(
                'ALTER TABLE crew_rate_cards ADD CONSTRAINT %s CHECK (
                    effective_to IS NULL OR effective_to >= effective_from
                )',
                self::EFFECTIVE_DATES_CHECK,
            ));

            $connection->statement(sprintf(
                'ALTER TABLE crew_rate_cards ADD CONSTRAINT %s CHECK (
                    hourly_rate_minor IS NOT NULL
                    OR daily_rate_minor IS NOT NULL
                    OR fixed_rate_minor IS NOT NULL
                )',
                self::RATE_PRESENT_CHECK,
            ));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_rate_cards');
    }
};
