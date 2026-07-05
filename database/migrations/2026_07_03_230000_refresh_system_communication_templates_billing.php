<?php

use App\Models\Store;
use App\Services\NotificationRegistry;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\NotificationTypeSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Seed billing communication templates (statement + refreshed copy) for existing stores.
     *
     * Only the new statement.emailed notification type is synced (upsert) — a
     * full NotificationTypeSeeder run here would pre-populate every type in
     * freshly migrated databases. Fresh installs get all types via seeders.
     */
    public function up(): void
    {
        $definition = NotificationTypeSeeder::types()['statement.emailed'] ?? null;

        if ($definition !== null) {
            $registry = new NotificationRegistry;
            $registry->registerMany(['statement.emailed' => $definition]);
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
