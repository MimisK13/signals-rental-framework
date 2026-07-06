<?php

namespace App\Data\Notifications;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class SaveCommunicationTemplateData extends Data
{
    /**
     * @param  array<string, mixed>|null  $attachments_config
     */
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        #[Required, Max(255)]
        public string $slug,
        #[Required, Max(50)]
        public string $channel,
        public ?string $subject = null,
        #[Required]
        public string $body = '',
        public ?int $notification_type_id = null,
        public ?int $store_id = null,
        public ?array $attachments_config = null,
        public bool $is_default = false,
        public bool $is_active = true,
        public ?string $change_note = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'channel' => ['required', 'string', 'max:50'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'notification_type_id' => ['nullable', 'integer', 'exists:notification_types,id'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'attachments_config' => ['nullable', 'array'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'change_note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
