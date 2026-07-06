<?php

use App\Models\Store;
use App\Services\NotificationRegistry;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\NotificationTypeSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Upsert P47 shortage/virtual-stock notification types for existing installs.
     *
     * Only the new/changed keys are synced — a full NotificationTypeSeeder run here
     * would pre-populate every type in freshly migrated databases. Fresh installs
     * get all types via seeders.
     */
    public function up(): void
    {
        $types = NotificationTypeSeeder::types();
        $definitions = array_filter([
            'shortage.escalated' => $types['shortage.escalated'] ?? null,
            'virtual_stock.overrun' => $types['virtual_stock.overrun'] ?? null,
        ]);

        if ($definitions !== []) {
            $registry = new NotificationRegistry;
            $registry->registerMany($definitions);
            $registry->syncToDatabase();
        }

        $seeder = app(CommunicationTemplateSeeder::class);

        Store::query()->each(function (Store $store) use ($seeder): void {
            $seeder->seedForStore($store);
        });
    }

    public function down(): void
    {
        // no-op — seeded templates remain; stores can customise via duplicate.
    }
};
