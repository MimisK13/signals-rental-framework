<?php

declare(strict_types=1);

namespace App\Domain\ReferenceData\Models;

use App\Contracts\HasSchema;
use App\Services\SchemaBuilder;
use App\Domain\ReferenceData\Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $code3
 * @property string $name
 * @property string|null $currency_code
 * @property string|null $phone_prefix
 * @property string|null $default_timezone
 * @property string|null $default_date_format
 * @property string|null $default_time_format
 * @property string|null $default_number_format
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Country extends Model implements HasSchema
{
    /** @use HasFactory<CountryFactory> */
    use HasFactory;

    protected static function newFactory(): CountryFactory
    {
        return CountryFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'code',
        'code3',
        'name',
        'currency_code',
        'phone_prefix',
        'default_timezone',
        'default_date_format',
        'default_time_format',
        'default_number_format',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('code')->label('Code')->required()->filterable()->sortable();
        $builder->string('code3')->label('ISO Alpha-3')->filterable();
        $builder->string('name')->label('Name')->required()->searchable()->filterable()->sortable();
        $builder->string('currency_code')->label('Currency Code')->filterable();
        $builder->string('phone_prefix')->label('Phone Prefix')->filterable();
        $builder->string('default_timezone')->label('Default Timezone');
        $builder->boolean('is_active')->label('Active')->filterable()->sortable()->groupable();
        $builder->datetime('created_at')->label('Created')->sortable();
        $builder->datetime('updated_at')->label('Updated')->sortable();
    }

    /**
     * Scope to active countries.
     *
     * @param  Builder<Country>  $query
     * @return Builder<Country>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
