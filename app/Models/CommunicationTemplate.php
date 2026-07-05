<?php

namespace App\Models;

use App\Models\Traits\HasStoreScoping;
use Database\Factories\CommunicationTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property array<string, mixed>|null $attachments_config
 * @property Carbon|null $last_edited_at
 */
class CommunicationTemplate extends Model
{
    /** @use HasFactory<CommunicationTemplateFactory> */
    use HasFactory, HasStoreScoping, SoftDeletes;

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
