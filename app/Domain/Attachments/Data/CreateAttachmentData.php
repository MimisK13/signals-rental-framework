<?php

declare(strict_types=1);

namespace App\Domain\Attachments\Data;

use Spatie\LaravelData\Data;

final class CreateAttachmentData extends Data
{
    public function __construct(
        public string $attachable_type,
        public int $attachable_id,
        public ?string $category = null,
        public ?string $description = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:20480'],
            'attachable_type' => ['required', 'string', 'in:Member,Repair,AssetInspection'],
            'attachable_id' => ['required', 'integer'],
            'category' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
