<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DATES_ORDERED_CHECK = 'crew_time_entries_dates_ordered_check';

    private const MINUTES_NON_NEGATIVE_CHECK = 'crew_time_entries_minutes_non_negative_check';

    public function up(): void
    {
        Schema::create('crew_time_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('crew_timesheet_id')->constrained('crew_timesheets')->cascadeOnDelete();
            $table->foreignId('crew_shift_id')->nullable()->constrained('crew_shifts')->nullOnDelete();
            $table->string('kind', 16)->default('work');
            $table->date('work_date');
            $table->dateTime('started_at');
            $table->dateTime('ended_at');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->integer('minutes');
            $table->boolean('is_overtime')->default(false);
            $table->decimal('multiplier', 4, 2)->nullable();
            $table->bigInteger('applied_rate_minor')->nullable();
            $table->bigInteger('amount_minor')->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->string('source', 16)->default('manual');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['crew_timesheet_id', 'work_date']);
            $table->index('crew_shift_id');
            $table->index(['crew_timesheet_id', 'kind']);
        });

        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement(sprintf(
                'ALTER TABLE crew_time_entries ADD CONSTRAINT %s CHECK (ended_at > started_at)',
                self::DATES_ORDERED_CHECK,
            ));

            $connection->statement(sprintf(
                'ALTER TABLE crew_time_entries ADD CONSTRAINT %s CHECK (minutes >= 0)',
                self::MINUTES_NON_NEGATIVE_CHECK,
            ));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_time_entries');
    }
};
