<?php

declare(strict_types=1);

namespace App\Domain\Currency\Models;

use App\Contracts\HasSchema;
use App\Domain\Currency\Database\Factories\CurrencyFactory;
use App\Services\SchemaBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $code
 * @property string $name
 * @property string $symbol
 * @property int $decimal_places
 * @property string $symbol_position
 * @property string $thousand_separator
 * @property string $decimal_separator
 * @property bool $is_enabled
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Currency extends Model implements HasSchema
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    protected static function newFactory(): CurrencyFactory
    {
        return CurrencyFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_places',
        'symbol_position',
        'thousand_separator',
        'decimal_separator',
        'is_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'decimal_places' => 'integer',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('code')->label('Code')->required()->filterable()->sortable();
        $builder->string('name')->label('Name')->required()->searchable()->filterable()->sortable();
        $builder->string('symbol')->label('Symbol')->filterable();
        $builder->integer('decimal_places')->label('Decimal Places');
        $builder->string('symbol_position')->label('Symbol Position')->filterable();
        $builder->boolean('is_enabled')->label('Enabled')->filterable()->sortable()->groupable();
        $builder->datetime('created_at')->label('Created')->sortable();
        $builder->datetime('updated_at')->label('Updated')->sortable();
    }

    /**
     * Scope to enabled currencies.
     *
     * @param  Builder<Currency>  $query
     * @return Builder<Currency>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }
}
