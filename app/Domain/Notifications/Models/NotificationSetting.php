<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Models;

use App\Domain\Notifications\Database\Factories\NotificationSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $notification_type_id
 * @property list<string>|null $channels
 * @property array<string, mixed>|null $channel_config
 * @property list<array<string, mixed>>|null $recipient_rules
 * @property bool $is_enabled
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class NotificationSetting extends Model
{
    /** @use HasFactory<NotificationSettingFactory> */
    use HasFactory;

    protected static function newFactory(): NotificationSettingFactory
    {
        return NotificationSettingFactory::new();
    }

    protected $fillable = [
        'notification_type_id',
        'channels',
        'channel_config',
        'recipient_rules',
        'is_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'channel_config' => 'array',
            'recipient_rules' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<NotificationType, $this>
     */
    public function notificationType(): BelongsTo
    {
        return $this->belongsTo(NotificationType::class);
    }
}
