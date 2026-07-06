<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CRITERIA_GIN_INDEX = 'surcharges_criteria_gin_idx';

    private const VALUE_ONE_OF_CHECK = 'surcharges_value_one_of_check';

    public function up(): void
    {
        Schema::create('surcharges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('surcharge_group_id')
                ->constrained('surcharge_groups')
                ->cascadeOnDelete();
            $table->string('name');
            $table->decimal('percent_value', 5, 2)->nullable();
            $table->integer('fixed_value_minor')->nullable();
            $table->jsonb('criteria')->nullable();
            $table->boolean('is_default')->default(false);
            $table->foreignId('product_tax_class_id')
                ->nullable()
                ->constrained('product_tax_classes')
                ->nullOnDelete();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('surcharge_group_id');
            $table->index(['surcharge_group_id', 'sort_order']);
        });

        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement(sprintf(
                'ALTER TABLE surcharges ADD CONSTRAINT %s CHECK (
                    (percent_value IS NOT NULL AND fixed_value_minor IS NULL)
                    OR (percent_value IS NULL AND fixed_value_minor IS NOT NULL)
                )',
                self::VALUE_ONE_OF_CHECK,
            ));

            $connection->statement(sprintf(
                'CREATE INDEX IF NOT EXISTS %s ON surcharges USING gin (criteria)',
                self::CRITERIA_GIN_INDEX,
            ));
        }
    }

    public function down(): void
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement(sprintf(
                'ALTER TABLE surcharges DROP CONSTRAINT IF EXISTS %s',
                self::VALUE_ONE_OF_CHECK,
            ));
            $connection->statement(sprintf('DROP INDEX IF EXISTS %s', self::CRITERIA_GIN_INDEX));
        }

        Schema::dropIfExists('surcharges');
    }
};
