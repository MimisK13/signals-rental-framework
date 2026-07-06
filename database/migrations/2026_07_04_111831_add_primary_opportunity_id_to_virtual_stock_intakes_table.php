<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_stock_intakes', function (Blueprint $table): void {
            $table->unsignedBigInteger('primary_opportunity_id')->nullable()->after('purchase_order_item_id');
            $table->foreign('primary_opportunity_id')->references('id')->on('opportunities')->nullOnDelete();
            $table->index('primary_opportunity_id');
        });

        $this->backfillPrimaryOpportunityIds();
    }

    public function down(): void
    {
        Schema::table('virtual_stock_intakes', function (Blueprint $table): void {
            $table->dropForeign(['primary_opportunity_id']);
            $table->dropIndex(['primary_opportunity_id']);
            $table->dropColumn('primary_opportunity_id');
        });
    }

    private function backfillPrimaryOpportunityIds(): void
    {
        DB::table('shortage_resolutions')
            ->whereNotNull('virtual_stock_intake_id')
            ->orderBy('id')
            ->chunkById(100, function ($resolutions): void {
                foreach ($resolutions as $resolution) {
                    /** @var array<string, mixed>|null $metadata */
                    $metadata = json_decode($resolution->metadata ?? 'null', true);

                    if (! is_array($metadata) || ! isset($metadata['opportunity_id'])) {
                        continue;
                    }

                    $opportunityId = (int) $metadata['opportunity_id'];

                    if ($opportunityId <= 0) {
                        continue;
                    }

                    DB::table('virtual_stock_intakes')
                        ->where('id', $resolution->virtual_stock_intake_id)
                        ->whereNull('primary_opportunity_id')
                        ->update(['primary_opportunity_id' => $opportunityId]);
                }
            });

        DB::table('virtual_stock_intakes')
            ->whereNull('primary_opportunity_id')
            ->whereNotNull('purchase_order_id')
            ->orderBy('id')
            ->chunkById(100, function ($intakes): void {
                foreach ($intakes as $intake) {
                    $opportunityId = DB::table('purchase_orders')
                        ->where('id', $intake->purchase_order_id)
                        ->value('opportunity_id');

                    if ($opportunityId === null) {
                        continue;
                    }

                    DB::table('virtual_stock_intakes')
                        ->where('id', $intake->id)
                        ->update(['primary_opportunity_id' => (int) $opportunityId]);
                }
            });
    }
};
