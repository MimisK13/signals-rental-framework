<?php

declare(strict_types=1);

namespace App\Domain\API\Models;

use App\Contracts\HasSchema;
use App\Domain\API\Database\Factories\RequestLogFactory;
use App\Models\User;
use App\Services\SchemaBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @property int $id
 * @property string $channel
 * @property string $request_id
 * @property string $method
 * @property string $uri
 * @property string|null $route_name
 * @property string|null $resource
 * @property string|null $operation
 * @property int|null $personal_access_token_id
 * @property int|null $user_id
 * @property string|null $ability
 * @property int $status_code
 * @property int $duration_ms
 * @property int $request_bytes
 * @property int $response_bytes
 * @property int $price_weight
 * @property int $weighted_units
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $request_body
 * @property string|null $response_body
 * @property Carbon|null $created_at
 */
final class RequestLog extends Model implements HasSchema
{
    /** @use HasFactory<RequestLogFactory> */
    use HasFactory;

    protected static function newFactory(): RequestLogFactory
    {
        return RequestLogFactory::new();
    }

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'channel',
        'request_id',
        'method',
        'uri',
        'route_name',
        'resource',
        'operation',
        'personal_access_token_id',
        'user_id',
        'ability',
        'status_code',
        'duration_ms',
        'request_bytes',
        'response_bytes',
        'price_weight',
        'weighted_units',
        'ip_address',
        'user_agent',
        'request_body',
        'response_body',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'duration_ms' => 'integer',
            'request_bytes' => 'integer',
            'response_bytes' => 'integer',
            'price_weight' => 'integer',
            'weighted_units' => 'integer',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('channel')->label('Channel')->filterable()->groupable();
        $builder->string('method')->label('Method')->filterable()->sortable();
        $builder->text('uri')->label('URI')->filterable();
        $builder->string('route_name')->label('Route')->filterable()->sortable();
        $builder->string('resource')->label('Resource')->filterable()->groupable();
        $builder->string('operation')->label('Operation')->filterable()->groupable();
        $builder->relation('personal_access_token_id')->label('Token')
            ->relation('token', 'belongsTo', PersonalAccessToken::class, 'name')
            ->filterable();
        $builder->relation('user_id')->label('User')
            ->relation('user', 'belongsTo', User::class, 'name')
            ->filterable();
        $builder->string('ability')->label('Ability')->filterable();
        $builder->integer('status_code')->label('Status')->filterable()->sortable();
        $builder->integer('duration_ms')->label('Duration (ms)')->sortable();
        $builder->integer('weighted_units')->label('Weighted Units')->sortable();
        $builder->string('ip_address')->label('IP Address')->filterable();
        $builder->datetime('created_at')->label('Created')->sortable()->filterable();
    }

    /**
     * @return BelongsTo<PersonalAccessToken, $this>
     */
    public function token(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'personal_access_token_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<RequestLog>  $query
     * @return Builder<RequestLog>
     */
    public function scopeForToken(Builder $query, int $tokenId): Builder
    {
        return $query->where('personal_access_token_id', $tokenId);
    }

    /**
     * @param  Builder<RequestLog>  $query
     * @return Builder<RequestLog>
     */
    public function scopeForStatus(Builder $query, int $statusCode): Builder
    {
        return $query->where('status_code', $statusCode);
    }

    /**
     * @param  Builder<RequestLog>  $query
     * @return Builder<RequestLog>
     */
    public function scopeCreatedBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }
}
