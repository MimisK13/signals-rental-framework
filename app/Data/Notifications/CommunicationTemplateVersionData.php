<?php

namespace App\Data\Notifications;

use App\Models\CommunicationTemplateVersion;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class CommunicationTemplateVersionData extends Data
{
    public function __construct(
        public int $id,
        public int $communication_template_id,
        public ?int $edited_by_member_id,
        public int $version,
        public ?string $subject,
        public string $body,
        public ?string $change_note,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(CommunicationTemplateVersion $version): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $version->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $version->updated_at;

        return new self(
            id: $version->id,
            communication_template_id: $version->communication_template_id,
            edited_by_member_id: $version->edited_by_member_id,
            version: $version->version,
            subject: $version->subject,
            body: $version->body,
            change_note: $version->change_note,
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
        );
    }
}
