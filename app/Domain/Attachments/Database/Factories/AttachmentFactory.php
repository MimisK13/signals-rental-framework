<?php

declare(strict_types=1);

namespace App\Domain\Attachments\Database\Factories;

use App\Domain\Attachments\Models\Attachment;
use App\Domain\Members\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Attachment> */
final class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'attachable_type' => Member::class,
            'attachable_id' => Member::factory(),
            'original_name' => fake()->word().'.pdf',
            'file_path' => 'attachments/'.fake()->uuid().'.pdf',
            'thumb_path' => null,
            'disk' => config('filesystems.default', 'local'),
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(1024, 10485760),
            'category' => null,
            'description' => null,
            'scan_status' => 'clean',
            'scanned_at' => null,
            'uploaded_by' => null,
        ];
    }

    public function image(): static
    {
        return $this->state(fn (): array => [
            'original_name' => fake()->word().'.jpg',
            'file_path' => 'attachments/'.fake()->uuid().'.jpg',
            'thumb_path' => 'attachments/thumbs/'.fake()->uuid().'.jpg',
            'mime_type' => 'image/jpeg',
        ]);
    }
}
