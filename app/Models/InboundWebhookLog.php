<?php

namespace App\Models;

use Database\Factories\InboundWebhookLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $headers
 * @property array<string, mixed>|null $payload
 */
class InboundWebhookLog extends Model
{
    /** @use HasFactory<InboundWebhookLogFactory> */
    use HasFactory;

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
