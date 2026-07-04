<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity_item_surcharges', function (Blueprint $table): void {
            $table->string('name')->nullable()->after('surcharge_id');
            $table->string('surcharge_type', 16)->nullable()->after('name');
            $table->decimal('percent_value', 5, 2)->nullable()->after('surcharge_type');
            $table->integer('fixed_value_minor')->nullable()->after('percent_value');
        });

        DB::statement(<<<'SQL'
            UPDATE opportunity_item_surcharges AS ois
            SET
                name = s.name,
                surcharge_type = CASE
                    WHEN s.percent_value IS NOT NULL THEN 'percent'
                    ELSE 'fixed'
                END,
                percent_value = s.percent_value,
                fixed_value_minor = s.fixed_value_minor
            FROM surcharges AS s
            WHERE ois.surcharge_id = s.id
              AND ois.name IS NULL
            SQL);

        Schema::table('opportunity_item_surcharges', function (Blueprint $table): void {
            $table->dropForeign(['surcharge_id']);
        });

        Schema::table('opportunity_item_surcharges', function (Blueprint $table): void {
            $table->unsignedBigInteger('surcharge_id')->nullable()->change();
        });

        Schema::table('opportunity_item_surcharges', function (Blueprint $table): void {
            $table->foreign('surcharge_id')
                ->references('id')
                ->on('surcharges')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('opportunity_item_surcharges', function (Blueprint $table): void {
            $table->dropForeign(['surcharge_id']);
        });

        Schema::table('opportunity_item_surcharges', function (Blueprint $table): void {
            $table->unsignedBigInteger('surcharge_id')->nullable(false)->change();
        });

        Schema::table('opportunity_item_surcharges', function (Blueprint $table): void {
            $table->foreign('surcharge_id')
                ->references('id')
                ->on('surcharges')
                ->cascadeOnDelete();
        });

        Schema::table('opportunity_item_surcharges', function (Blueprint $table): void {
            $table->dropColumn(['name', 'surcharge_type', 'percent_value', 'fixed_value_minor']);
        });
    }
};
