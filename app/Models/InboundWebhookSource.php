<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Services\SchemaBuilder;
use Database\Factories\InboundWebhookSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InboundWebhookSource extends Model implements HasSchema
{
    /** @use HasFactory<InboundWebhookSourceFactory> */
    use HasFactory;

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
