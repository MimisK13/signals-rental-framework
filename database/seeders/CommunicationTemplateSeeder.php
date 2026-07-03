<?php

namespace Database\Seeders;

use App\Models\CommunicationTemplate;
use App\Models\NotificationType;
use App\Models\Store;
use App\Services\Notifications\SystemCommunicationTemplates;
use Illuminate\Database\Seeder;

class CommunicationTemplateSeeder extends Seeder
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
