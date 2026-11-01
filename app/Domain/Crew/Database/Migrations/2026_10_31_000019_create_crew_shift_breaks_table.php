<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DATES_ORDERED_CHECK = 'crew_shift_breaks_dates_ordered_check';

    public function up(): void
    {
        Schema::create('crew_shift_breaks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('crew_shift_id')->constrained('crew_shifts')->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('is_paid')->default(false);
            $table->string('label', 60)->nullable();

            $table->index(['crew_shift_id', 'starts_at']);
        });

        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement(sprintf(
                'ALTER TABLE crew_shift_breaks ADD CONSTRAINT %s CHECK (ends_at > starts_at)',
                self::DATES_ORDERED_CHECK,
            ));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_shift_breaks');
    }
};
