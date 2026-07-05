<?php

namespace App\Data\Notifications;

use App\Models\NotificationType;
use Spatie\LaravelData\Data;

class NotificationPreferenceData extends Data
{
    /**
     * @param  list<string>  $tenant_channels
     * @param  list<string>  $channels
     */
    public function __construct(
        public int $notification_type_id,
        public string $notification_type_key,
        public string $category,
        public string $name,
        public ?string $description,
        public array $tenant_channels,
        public array $channels,
        public bool $is_muted,
    ) {}

    /**
     * @param  array{
     *     type: NotificationType,
     *     tenant_channels: list<string>,
     *     member_channels: list<string>,
     *     is_muted: bool
     * }  $row
     */
    public static function fromCatalogRow(array $row): self
    {
        /** @var NotificationType $type */
        $type = $row['type'];

        return new self(
            notification_type_id: $type->id,
            notification_type_key: $type->key,
            category: $type->category,
            name: $type->name,
            description: $type->description,
            tenant_channels: $row['tenant_channels'],
            channels: $row['member_channels'],
            is_muted: $row['is_muted'],
        );
    }
}
