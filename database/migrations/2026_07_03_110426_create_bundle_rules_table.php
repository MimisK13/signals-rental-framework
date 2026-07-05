<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CRITERIA_GIN_INDEX = 'bundle_rules_criteria_gin_idx';

    private const VALUE_ONE_OF_CHECK = 'bundle_rules_value_one_of_check';

    public function up(): void
    {
        Schema::create('bundle_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('bundle_percent', 5, 2)->nullable();
            $table->integer('bundle_fixed_price_minor')->nullable();
            $table->jsonb('criteria')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });

        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement(sprintf(
                'ALTER TABLE bundle_rules ADD CONSTRAINT %s CHECK (
                    (bundle_percent IS NOT NULL AND bundle_fixed_price_minor IS NULL)
                    OR (bundle_percent IS NULL AND bundle_fixed_price_minor IS NOT NULL)
                )',
                self::VALUE_ONE_OF_CHECK,
            ));

            $connection->statement(sprintf(
                'CREATE INDEX IF NOT EXISTS %s ON bundle_rules USING gin (criteria)',
                self::CRITERIA_GIN_INDEX,
            ));
        }
    }

    public function down(): void
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement(sprintf(
                'ALTER TABLE bundle_rules DROP CONSTRAINT IF EXISTS %s',
                self::VALUE_ONE_OF_CHECK,
            ));
            $connection->statement(sprintf('DROP INDEX IF EXISTS %s', self::CRITERIA_GIN_INDEX));
        }

        Schema::dropIfExists('bundle_rules');
    }
};
