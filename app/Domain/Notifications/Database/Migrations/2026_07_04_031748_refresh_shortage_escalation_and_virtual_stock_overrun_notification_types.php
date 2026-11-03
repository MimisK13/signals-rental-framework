<?php

declare(strict_types=1);

use App\Domain\Notifications\Database\Seeders\NotificationTypeSeeder;
use App\Domain\Notifications\Services\NotificationRegistry;
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

    }

    public function down(): void
    {
        // no-op — notification type definitions are forward-only data.
    }
};
