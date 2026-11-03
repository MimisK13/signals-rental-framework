<?php

declare(strict_types=1);

namespace App\Domain\Members\Models;

use App\Contracts\HasSchema;
use App\Domain\Lists\Models\ListValue;
use App\Domain\Members\Database\Factories\PhoneFactory;
use App\Services\SchemaBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $phoneable_type
 * @property int $phoneable_id
 * @property string $number
 * @property string|null $country_code
 * @property int|null $type_id
 * @property bool $is_primary
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Phone extends Model implements HasSchema
{
    /** @use HasFactory<PhoneFactory> */
    use HasFactory;

    protected static function newFactory(): PhoneFactory
    {
        return PhoneFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'phoneable_type',
        'phoneable_id',
        'number',
        'country_code',
        'type_id',
        'is_primary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('number')->label('Number')->required()->searchable()->filterable()->sortable();
        $builder->string('country_code')->label('Country Code')->filterable();
        $builder->relation('type_id')->label('Type')
            ->relation('type', 'belongsTo', ListValue::class, 'name')
            ->filterable();
        $builder->boolean('is_primary')->label('Primary')->filterable()->sortable();
        $builder->datetime('created_at')->label('Created')->sortable();
        $builder->datetime('updated_at')->label('Updated')->sortable();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function phoneable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<ListValue, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(ListValue::class, 'type_id');
    }
}
