<?php

namespace App\Models;

use Database\Factories\UsageDailyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

class UsageDaily extends Model
{
    /** @use HasFactory<UsageDailyFactory> */
    use HasFactory;

    protected $table = 'usage_daily';

    /** @var list<string> */
    protected $fillable = [
        'dedupe_key',
        'date',
        'channel',
        'personal_access_token_id',
        'inbound_webhook_source_id',
        'key',
        'resource',
        'status_class',
        'request_count',
        'weighted_units',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'request_count' => 'integer',
            'weighted_units' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (UsageDaily $usageDaily): void {
            if ($usageDaily->getAttribute('dedupe_key') === null) {
                $usageDaily->dedupe_key = self::computeDedupeKey(
                    $usageDaily->date,
                    $usageDaily->channel,
                    $usageDaily->key,
                    $usageDaily->personal_access_token_id,
                    $usageDaily->inbound_webhook_source_id,
                    $usageDaily->status_class,
                );
            }
        });
    }

    public static function computeDedupeKey(
        Carbon|string $date,
        string $channel,
        string $key,
        ?int $personalAccessTokenId,
        ?int $inboundWebhookSourceId,
        string $statusClass,
    ): string {
        $dateString = $date instanceof Carbon ? $date->toDateString() : $date;

        return hash('sha256', implode('|', [
            $dateString,
            $channel,
            $key,
            $personalAccessTokenId ?? '-',
            $inboundWebhookSourceId ?? '-',
            $statusClass,
        ]));
    }

    /**
     * @return BelongsTo<PersonalAccessToken, $this>
     */
    public function token(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'personal_access_token_id');
    }

    /**
     * @return BelongsTo<InboundWebhookSource, $this>
     */
    public function inboundWebhookSource(): BelongsTo
    {
        return $this->belongsTo(InboundWebhookSource::class);
    }
}
