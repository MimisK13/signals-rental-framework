<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CRITERIA_GIN_INDEX = 'discount_rules_criteria_gin_idx';

    public function up(): void
    {
        Schema::create('discount_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('discount_category_id')
                ->constrained('discount_categories')
                ->cascadeOnDelete();
            $table->decimal('discount_percent', 5, 2);
            $table->jsonb('criteria')->nullable();
            $table->string('rule_scope', 32)->default('per_line');
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('discount_category_id');
            $table->index(['discount_category_id', 'sort_order']);
            $table->index(['discount_category_id', 'rule_scope']);
        });

        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement(sprintf(
                'CREATE INDEX IF NOT EXISTS %s ON discount_rules USING gin (criteria)',
                self::CRITERIA_GIN_INDEX,
            ));
        }
    }

    public function down(): void
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement(sprintf('DROP INDEX IF EXISTS %s', self::CRITERIA_GIN_INDEX));
        }

        Schema::dropIfExists('discount_rules');
    }
};
