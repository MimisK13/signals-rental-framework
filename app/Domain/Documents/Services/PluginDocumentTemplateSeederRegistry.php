<?php

declare(strict_types=1);

namespace App\Domain\Documents\Services;

use App\Domain\Stores\Models\Store;

/**
 * Collects plugin-provided document template seeders invoked by
 * {@see App\Domain\Documents\Database\Seeders\DocumentTemplateSeeder}.
 */
class PluginDocumentTemplateSeederRegistry
{
    /** @var list<callable(Store): void> */
    private array $seeders = [];

    public function register(callable $seeder): void
    {
        $this->seeders[] = $seeder;
    }

    public function seedForStore(Store $store): void
    {
        foreach ($this->seeders as $seeder) {
            $seeder($store);
        }
    }

    /**
     * @return list<callable(Store): void>
     */
    public function all(): array
    {
        return $this->seeders;
    }
}
