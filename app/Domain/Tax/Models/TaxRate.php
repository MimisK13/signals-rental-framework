<?php

declare(strict_types=1);

namespace App\Domain\Tax\Models;

use App\Contracts\HasSchema;
use App\Domain\Tax\Database\Factories\TaxRateFactory;
use App\Services\SchemaBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $rate
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class TaxRate extends Model implements HasSchema
{
    /** @use HasFactory<TaxRateFactory> */
    use HasFactory;

    protected static function newFactory(): TaxRateFactory
    {
        return TaxRateFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'rate',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('name')->label('Name')->required()->searchable()->filterable()->sortable();
        $builder->text('description')->label('Description')->searchable();
        $builder->decimal('rate')->label('Rate')->required()->sortable();
        $builder->boolean('is_active')->label('Active')->filterable()->sortable()->groupable();
        $builder->datetime('created_at')->label('Created')->sortable();
        $builder->datetime('updated_at')->label('Updated')->sortable();
    }

    /**
     * @return HasMany<TaxRule, $this>
     */
    public function taxRules(): HasMany
    {
        return $this->hasMany(TaxRule::class);
    }
}
