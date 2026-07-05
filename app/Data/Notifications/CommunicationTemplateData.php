<?php

namespace App\Data\Notifications;

use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateVersion;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class CommunicationTemplateData extends Data
{
    /**
     * @param  array<string, mixed>|null  $attachments_config
     * @param  list<CommunicationTemplateVersionData>|null  $versions
     */
    public function __construct(
        public int $id,
        public ?int $notification_type_id,
        public ?int $store_id,
        public string $name,
        public string $slug,
        public string $channel,
        public ?string $subject,
        public string $body,
        public ?array $attachments_config,
        public bool $is_default,
        public bool $is_system,
        public bool $is_active,
        public int $version,
        public ?string $last_edited_at,
        public string $created_at,
        public string $updated_at,
        public ?array $versions = null,
    ) {}

    public static function fromModel(CommunicationTemplate $template, bool $includeVersions = false): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $template->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $template->updated_at;

        $versions = null;

        if ($includeVersions) {
            $template->loadMissing('versions');

            $versions = $template->versions
                ->map(fn (CommunicationTemplateVersion $version): CommunicationTemplateVersionData => CommunicationTemplateVersionData::fromModel($version))
                ->values()
                ->all();
        }

        return new self(
            id: $template->id,
            notification_type_id: $template->notification_type_id,
            store_id: $template->store_id,
            name: $template->name,
            slug: $template->slug,
            channel: $template->channel,
            subject: $template->subject,
            body: $template->body,
            attachments_config: $template->attachments_config,
            is_default: $template->is_default,
            is_system: $template->is_system,
            is_active: $template->is_active,
            version: $template->version,
            last_edited_at: $template->last_edited_at?->toIso8601String(),
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
            versions: $versions,
        );
    }
}
