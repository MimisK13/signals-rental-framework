<?php

declare(strict_types=1);

namespace App\Domain\Members\Models;

use App\Contracts\HasSchema;
use App\Domain\Lists\Models\ListValue;
use App\Domain\Members\Database\Factories\AddressFactory;
use App\Domain\ReferenceData\Models\Country;
use App\Services\SchemaBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $addressable_type
 * @property int $addressable_id
 * @property string|null $name
 * @property string|null $street
 * @property string|null $city
 * @property string|null $county
 * @property string|null $postcode
 * @property string|null $what3words
 * @property int|null $country_id
 * @property int|null $type_id
 * @property bool $is_primary
 * @property string|null $latitude
 * @property string|null $longitude
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Address extends Model implements HasSchema
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory;

    protected static function newFactory(): AddressFactory
    {
        return AddressFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'addressable_type',
        'addressable_id',
        'name',
        'street',
        'city',
        'county',
        'postcode',
        'what3words',
        'country_id',
        'type_id',
        'is_primary',
        'latitude',
        'longitude',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('name')->label('Name')->searchable()->filterable()->sortable();
        $builder->string('street')->label('Street')->searchable();
        $builder->string('city')->label('City')->filterable()->sortable()->searchable();
        $builder->string('county')->label('County')->filterable();
        $builder->string('postcode')->label('Postcode')->filterable()->searchable();
        $builder->string('what3words')->label('what3words')->searchable()->filterable();
        $builder->relation('country_id')->label('Country')
            ->relation('country', 'belongsTo', Country::class, 'name')
            ->filterable();
        $builder->relation('type_id')->label('Type')
            ->relation('type', 'belongsTo', ListValue::class, 'name')
            ->filterable();
        $builder->boolean('is_primary')->label('Primary')->filterable()->sortable();
        $builder->decimal('latitude')->label('Latitude');
        $builder->decimal('longitude')->label('Longitude');
        $builder->datetime('created_at')->label('Created')->sortable();
        $builder->datetime('updated_at')->label('Updated')->sortable();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<ListValue, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(ListValue::class, 'type_id');
    }
}
