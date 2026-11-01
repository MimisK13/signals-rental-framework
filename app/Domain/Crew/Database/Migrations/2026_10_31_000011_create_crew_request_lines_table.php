<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const QUANTITY_POSITIVE_CHECK = 'crew_request_lines_quantity_positive_check';

    private const FILLED_WITHIN_QUANTITY_CHECK = 'crew_request_lines_filled_within_quantity_check';

    private const DATES_ORDERED_CHECK = 'crew_request_lines_dates_ordered_check';

    public function up(): void
    {
        Schema::create('crew_request_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('crew_request_id')->constrained('crew_requests')->cascadeOnDelete();
            $table->foreignId('crew_role_id')->constrained('crew_roles')->restrictOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->unsignedSmallInteger('filled_quantity')->default(0);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->string('min_proficiency', 16)->nullable();
            $table->text('notes')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['crew_request_id', 'crew_role_id']);
            $table->index('crew_request_id');
        });

        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement(sprintf(
                'ALTER TABLE crew_request_lines ADD CONSTRAINT %s CHECK (quantity > 0)',
                self::QUANTITY_POSITIVE_CHECK,
            ));

            $connection->statement(sprintf(
                'ALTER TABLE crew_request_lines ADD CONSTRAINT %s CHECK (filled_quantity <= quantity)',
                self::FILLED_WITHIN_QUANTITY_CHECK,
            ));

            $connection->statement(sprintf(
                'ALTER TABLE crew_request_lines ADD CONSTRAINT %s CHECK (
                    ends_at IS NULL OR starts_at IS NULL OR ends_at > starts_at
                )',
                self::DATES_ORDERED_CHECK,
            ));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_request_lines');
    }
};
