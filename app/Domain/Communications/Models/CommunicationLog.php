<?php

declare(strict_types=1);

namespace App\Domain\Communications\Models;

use App\Domain\Communications\Database\Factories\CommunicationLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $notification_type_key
 * @property string $channel
 * @property int|null $provider_id
 * @property int|null $template_id
 * @property string $notifiable_type
 * @property int $notifiable_id
 * @property string $subject_type
 * @property int $subject_id
 * @property string|null $recipient_address
 * @property string|null $subject_line
 * @property string|null $rendered_body
 * @property list<string>|null $attachments
 * @property string $status
 * @property string|null $failure_reason
 * @property string|null $message_id
 * @property bool $is_pinned
 * @property Carbon|null $queued_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $opened_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class CommunicationLog extends Model
{
    /** @use HasFactory<CommunicationLogFactory> */
    use HasFactory;

    protected static function newFactory(): CommunicationLogFactory
    {
        return CommunicationLogFactory::new();
    }

    protected $table = 'communication_log';

    /** @var list<string> */
    protected $fillable = [
        'notification_type_key',
        'channel',
        'provider_id',
        'template_id',
        'notifiable_type',
        'notifiable_id',
        'subject_type',
        'subject_id',
        'recipient_address',
        'subject_line',
        'rendered_body',
        'attachments',
        'status',
        'failure_reason',
        'message_id',
        'is_pinned',
        'queued_at',
        'sent_at',
        'delivered_at',
        'opened_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'is_pinned' => 'boolean',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
