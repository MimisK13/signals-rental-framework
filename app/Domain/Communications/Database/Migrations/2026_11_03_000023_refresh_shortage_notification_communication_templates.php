<?php

declare(strict_types=1);

use App\Domain\Communications\Database\Seeders\CommunicationTemplateSeeder;
use App\Domain\Stores\Models\Store;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $seeder = app(CommunicationTemplateSeeder::class);

        Store::query()->each(function (Store $store) use ($seeder): void {
            $seeder->seedForStore($store);
        });
    }

    public function down(): void
    {
        // no-op — stores may have customized the seeded templates.
    }
};
