<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @property int $id
 * @property string $key
 * @property int $personal_access_token_id
 * @property string $request_fingerprint
 * @property int|null $response_status
 * @property array<string, mixed>|null $response_body
 * @property CarbonInterface $expires_at
 * @property CarbonInterface $created_at
 */
class ApiIdempotencyKey extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'key',
        'personal_access_token_id',
        'request_fingerprint',
        'response_status',
        'response_body',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_status' => 'integer',
            'response_body' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PersonalAccessToken, $this>
     */
    public function token(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'personal_access_token_id');
    }

    /**
     * Whether the original request is still being processed.
     */
    public function isInFlight(): bool
    {
        return $this->response_status === null;
    }

    /**
     * Whether a deterministic response has been stored for replay.
     */
    public function isCompleted(): bool
    {
        return $this->response_status !== null;
    }

    /**
     * Rows past their TTL are safe to prune. Run by the scheduled `model:prune`
     * command (see routes/console.php).
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<', now());
    }
}
