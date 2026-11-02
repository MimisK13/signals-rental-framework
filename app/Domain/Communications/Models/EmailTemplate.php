<?php

declare(strict_types=1);

namespace App\Domain\Communications\Models;

use App\Domain\Communications\Database\Factories\EmailTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string $subject
 * @property string $body_markdown
 * @property string|null $description
 * @property list<string>|null $available_merge_fields
 * @property bool $is_system
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class EmailTemplate extends Model
{
    /** @use HasFactory<EmailTemplateFactory> */
    use HasFactory;

    protected static function newFactory(): EmailTemplateFactory
    {
        return EmailTemplateFactory::new();
    }

    protected $fillable = [
        'key',
        'name',
        'subject',
        'body_markdown',
        'description',
        'available_merge_fields',
        'is_system',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'available_merge_fields' => 'array',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<EmailTemplateVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(EmailTemplateVersion::class)->orderByDesc('version_number');
    }

    public function latestVersionNumber(): int
    {
        return (int) $this->versions()->max('version_number');
    }
}
