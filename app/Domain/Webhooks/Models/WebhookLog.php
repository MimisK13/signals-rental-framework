<?php

declare(strict_types=1);

namespace App\Domain\Webhooks\Models;

use App\Domain\Webhooks\Database\Factories\WebhookLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $webhook_id
 * @property string $event
 * @property array<string, mixed>|null $payload
 * @property int|null $response_code
 * @property string|null $response_body
 * @property int $attempts
 * @property int $price_weight
 * @property int $weighted_units
 * @property Carbon|null $delivered_at
 * @property Carbon|null $next_retry_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class WebhookLog extends Model
{
    /** @use HasFactory<WebhookLogFactory> */
    use HasFactory;

    protected static function newFactory(): WebhookLogFactory
    {
        return WebhookLogFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'webhook_id',
        'event',
        'payload',
        'response_code',
        'response_body',
        'attempts',
        'delivered_at',
        'next_retry_at',
        'price_weight',
        'weighted_units',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response_code' => 'integer',
            'attempts' => 'integer',
            'price_weight' => 'integer',
            'weighted_units' => 'integer',
            'delivered_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Webhook, $this>
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
