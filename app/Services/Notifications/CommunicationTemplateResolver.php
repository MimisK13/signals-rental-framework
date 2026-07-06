<?php

namespace App\Services\Notifications;

use App\Models\CommunicationTemplate;
use App\Models\NotificationType;

/**
 * Resolves the active default communication template for a store and notification type.
 */
class CommunicationTemplateResolver
{
    public function hasActiveTemplate(int $storeId, string $notificationTypeKey): bool
    {
        return $this->resolveForNotification($storeId, $notificationTypeKey) !== null;
    }

    public function resolveForNotification(int $storeId, string $notificationTypeKey): ?CommunicationTemplate
    {
        $notificationTypeId = NotificationType::query()
            ->where('key', $notificationTypeKey)
            ->where('is_active', true)
            ->value('id');

        if ($notificationTypeId === null) {
            return null;
        }

        return CommunicationTemplate::query()
            ->where('store_id', $storeId)
            ->where('notification_type_id', $notificationTypeId)
            ->where('channel', 'email')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }
}
