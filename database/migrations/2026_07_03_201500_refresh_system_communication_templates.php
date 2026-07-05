<?php

use App\Models\Store;
use Database\Seeders\CommunicationTemplateSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Seed bundled communication templates for stores created before P26.
     *
     * Uses firstOrCreate semantics — existing templates (including customised
     * copies sharing the same slug) are never overwritten.
     */
    public function up(): void
    {
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
