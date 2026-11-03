<?php

declare(strict_types=1);

namespace App\Domain\Webhooks\Models;

use App\Contracts\HasSchema;
use App\Domain\Webhooks\Database\Factories\InboundWebhookSourceFactory;
use App\Services\SchemaBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $secret
 * @property string $signature_algo
 * @property string $signature_header
 * @property list<string>|null $expected_events
 * @property int $price_weight
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class InboundWebhookSource extends Model implements HasSchema
{
    /** @use HasFactory<InboundWebhookSourceFactory> */
    use HasFactory;

    protected static function newFactory(): InboundWebhookSourceFactory
    {
        return InboundWebhookSourceFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'secret',
        'signature_algo',
        'signature_header',
        'expected_events',
        'price_weight',
        'is_active',
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
            'expected_events' => 'array',
            'price_weight' => 'integer',
            'is_active' => 'boolean',
            'secret' => 'encrypted',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('name')->label('Name')->required()->searchable()->filterable()->sortable();
        $builder->string('slug')->label('Slug')->required()->filterable()->sortable();
        $builder->text('description')->label('Description');
        $builder->json('expected_events')->label('Expected Events');
        $builder->integer('price_weight')->label('Price Weight')->sortable();
        $builder->boolean('is_active')->label('Active')->filterable()->sortable()->groupable();
        $builder->datetime('created_at')->label('Created')->sortable();
        $builder->datetime('updated_at')->label('Updated')->sortable();
    }

    /**
     * @return HasMany<InboundWebhookLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(InboundWebhookLog::class);
    }
}
