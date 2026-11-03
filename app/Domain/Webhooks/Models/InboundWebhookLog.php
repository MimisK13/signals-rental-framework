<?php

declare(strict_types=1);

namespace App\Domain\Webhooks\Models;

use App\Domain\Webhooks\Database\Factories\InboundWebhookLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $inbound_webhook_source_id
 * @property string|null $event
 * @property array<string, mixed>|null $headers
 * @property array<string, mixed>|null $payload
 * @property bool $signature_valid
 * @property int $status_code
 * @property string $request_id
 * @property int $price_weight
 * @property int $weighted_units
 * @property Carbon|null $created_at
 */
final class InboundWebhookLog extends Model
{
    /** @use HasFactory<InboundWebhookLogFactory> */
    use HasFactory;

    protected static function newFactory(): InboundWebhookLogFactory
    {
        return InboundWebhookLogFactory::new();
    }

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'inbound_webhook_source_id',
        'event',
        'headers',
        'payload',
        'signature_valid',
        'status_code',
        'request_id',
        'price_weight',
        'weighted_units',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'payload' => 'array',
            'signature_valid' => 'boolean',
            'status_code' => 'integer',
            'price_weight' => 'integer',
            'weighted_units' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<InboundWebhookSource, $this>
     */
    public function inboundWebhookSource(): BelongsTo
    {
        return $this->belongsTo(InboundWebhookSource::class);
    }
}
