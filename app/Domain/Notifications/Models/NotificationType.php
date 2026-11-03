<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Models;

use App\Domain\Notifications\Database\Factories\NotificationTypeFactory;
use App\Domain\Notifications\Enums\NotificationAudience;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $key
 * @property string $category
 * @property string $name
 * @property string|null $description
 * @property list<string> $default_channels
 * @property list<string> $available_channels
 * @property list<array<string, mixed>>|null $default_recipients
 * @property NotificationAudience $audience
 * @property array<string, mixed>|null $data_schema
 * @property bool $is_active
 * @property bool $is_system
 * @property string $source
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class NotificationType extends Model
{
    /** @use HasFactory<NotificationTypeFactory> */
    use HasFactory;

    protected static function newFactory(): NotificationTypeFactory
    {
        return NotificationTypeFactory::new();
    }

    protected $fillable = [
        'key',
        'category',
        'name',
        'description',
        'audience',
        'available_channels',
        'default_channels',
        'default_recipients',
        'data_schema',
        'is_active',
        'is_system',
        'source',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience' => NotificationAudience::class,
            'available_channels' => 'array',
            'default_channels' => 'array',
            'default_recipients' => 'array',
            'data_schema' => 'array',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return HasOne<NotificationSetting, $this>
     */
    public function setting(): HasOne
    {
        return $this->hasOne(NotificationSetting::class);
    }

    /**
     * @return HasMany<NotificationPreference, $this>
     */
    public function preferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /**
     * Get the effective channels for this notification type (system override or defaults).
     *
     * @return list<string>
     */
    public function effectiveChannels(): array
    {
        $setting = $this->setting;

        if ($setting && ! $setting->is_enabled) {
            return [];
        }

        if ($setting && $setting->channels !== null) {
            /** @var list<string> */
            return $setting->channels;
        }

        /** @var list<string> */
        return $this->default_channels;
    }
}
