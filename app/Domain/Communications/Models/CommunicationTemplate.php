<?php

declare(strict_types=1);

namespace App\Domain\Communications\Models;

use App\Domain\Communications\Database\Factories\CommunicationTemplateFactory;
use App\Models\NotificationType;
use App\Models\Store;
use App\Models\Traits\HasStoreScoping;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $notification_type_id
 * @property int|null $store_id
 * @property string $name
 * @property string $slug
 * @property string $channel
 * @property string|null $subject
 * @property string $body
 * @property array<string, mixed>|null $attachments_config
 * @property bool $is_default
 * @property bool $is_system
 * @property bool $is_active
 * @property int $version
 * @property Carbon|null $last_edited_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
final class CommunicationTemplate extends Model
{
    /** @use HasFactory<CommunicationTemplateFactory> */
    use HasFactory, HasStoreScoping, SoftDeletes;

    protected static function newFactory(): CommunicationTemplateFactory
    {
        return CommunicationTemplateFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'notification_type_id',
        'store_id',
        'name',
        'slug',
        'channel',
        'subject',
        'body',
        'attachments_config',
        'is_default',
        'is_system',
        'is_active',
        'version',
        'last_edited_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attachments_config' => 'array',
            'is_default' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'version' => 'integer',
            'last_edited_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<NotificationType, $this>
     */
    public function notificationType(): BelongsTo
    {
        return $this->belongsTo(NotificationType::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<CommunicationTemplateVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(CommunicationTemplateVersion::class)->orderByDesc('version');
    }
}
