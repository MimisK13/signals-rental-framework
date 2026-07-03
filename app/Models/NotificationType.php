<?php

namespace App\Models;

use App\Enums\NotificationAudience;
use Database\Factories\NotificationTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property list<string> $default_channels
 * @property list<string> $available_channels
 * @property list<array<string, mixed>>|null $default_recipients
 * @property NotificationAudience $audience
 * @property array<string, mixed>|null $data_schema
 */
class NotificationType extends Model
{
    /** @use HasFactory<NotificationTypeFactory> */
    use HasFactory;

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
