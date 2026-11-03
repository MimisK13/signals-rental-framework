<?php

declare(strict_types=1);

namespace App\Domain\Communications\Database\Seeders;

use App\Domain\Communications\Models\CommunicationTemplate;
use App\Domain\Communications\Services\SystemCommunicationTemplates;
use App\Domain\Notifications\Models\NotificationType;
use App\Domain\Stores\Models\Store;
use Illuminate\Database\Seeder;

final class CommunicationTemplateSeeder extends Seeder
{
    /**
     * Seed system communication templates for every existing store.
     */
    public function run(): void
    {
        Store::query()->each(function (Store $store): void {
            $this->seedForStore($store);
        });
    }

    /**
     * Seed (or skip) the system communication template set for one store. Idempotent.
     */
    public function seedForStore(Store $store): void
    {
        $typesByKey = NotificationType::query()
            ->whereIn('key', SystemCommunicationTemplates::seededTypeKeys())
            ->pluck('id', 'key');

        foreach (SystemCommunicationTemplates::definitions() as $definition) {
            $notificationTypeId = $typesByKey->get($definition['notification_type_key']);

            if ($notificationTypeId === null) {
                continue;
            }

            CommunicationTemplate::query()->firstOrCreate(
                [
                    'store_id' => $store->id,
                    'slug' => $definition['slug'],
                ],
                [
                    'notification_type_id' => $notificationTypeId,
                    'name' => $definition['name'],
                    'channel' => $definition['channel'],
                    'subject' => $definition['subject'],
                    'body' => $definition['body'],
                    'attachments_config' => $definition['attachments_config'],
                    'is_default' => true,
                    'is_system' => true,
                    'is_active' => true,
                    'version' => 1,
                    'last_edited_at' => now(),
                ],
            );
        }
    }
}
