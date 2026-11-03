<?php

declare(strict_types=1);

namespace App\Domain\Webhooks\Models;

use App\Contracts\HasSchema;
use App\Domain\Webhooks\Database\Factories\WebhookFactory;
use App\Models\User;
use App\Services\SchemaBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $url
 * @property string $secret
 * @property list<string>|null $events
 * @property bool $is_active
 * @property int $consecutive_failures
 * @property Carbon|null $disabled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Webhook extends Model implements HasSchema
{
    /** @use HasFactory<WebhookFactory> */
    use HasFactory;

    protected static function newFactory(): WebhookFactory
    {
        return WebhookFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'url',
        'secret',
        'events',
        'is_active',
        'consecutive_failures',
        'disabled_at',
    ];

    /** @var list<string> */
    protected $hidden = [
        'secret',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'consecutive_failures' => 'integer',
            'disabled_at' => 'datetime',
            'secret' => 'encrypted',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('url')->label('URL')->required()->searchable()->filterable()->sortable();
        $builder->json('events')->label('Events');
        $builder->boolean('is_active')->label('Active')->filterable()->sortable()->groupable();
        $builder->integer('consecutive_failures')->label('Consecutive Failures')->sortable();
        $builder->datetime('disabled_at')->label('Disabled At')->filterable()->sortable();
        $builder->relation('user_id')->label('User')
            ->relation('user', 'belongsTo', User::class, 'name')
            ->filterable();
        $builder->datetime('created_at')->label('Created')->sortable();
        $builder->datetime('updated_at')->label('Updated')->sortable();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<WebhookLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(WebhookLog::class);
    }

    /**
     * Check if this webhook is subscribed to a given event.
     */
    public function subscribedTo(string $event): bool
    {
        /** @var list<string> $events */
        $events = $this->events ?? [];

        return in_array('*', $events, true) || in_array($event, $events, true);
    }
}
