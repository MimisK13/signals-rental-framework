<?php

declare(strict_types=1);

namespace App\Domain\Stores\Models;

use App\Contracts\HasSchema;
use App\Domain\Availability\Shortages\Enums\ShortageDispatchPolicy;
use App\Domain\Availability\Shortages\Enums\ShortagePolicy;
use App\Domain\CustomFields\Concerns\HasCustomFields;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Numbering\Models\DocumentSequence;
use App\Domain\ReferenceData\Models\Country;
use App\Domain\Stock\Stocktakes\Models\Stocktake;
use App\Domain\Stores\Database\Factories\StoreFactory;
use App\Domain\Tax\Models\TaxZone;
use App\Services\SchemaBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $street
 * @property string|null $city
 * @property string|null $county
 * @property string|null $postcode
 * @property string|null $country_code
 * @property int|null $country_id
 * @property int|null $tax_zone_id
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $timezone
 * @property bool $is_default
 * @property ShortagePolicy $shortage_policy
 * @property ShortageDispatchPolicy $shortage_dispatch_policy
 * @property bool $shortage_auto_resolve_enabled
 * @property list<string>|null $shortage_preferred_resolvers
 * @property array<string, mixed>|null $operating_hours
 * @property bool $is_virtual
 * @property bool $include_in_default_queries
 * @property list<string>|null $tag_list
 * @property string|null $brand_logo_path
 * @property string|null $brand_primary_colour
 * @property string|null $brand_secondary_colour
 * @property string|null $brand_font_family
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Store extends Model implements HasSchema
{
    /** @use HasFactory<StoreFactory> */
    use HasCustomFields, HasFactory;

    protected static function newFactory(): StoreFactory
    {
        return StoreFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'name',
        'street',
        'city',
        'county',
        'postcode',
        'country_code',
        'country_id',
        'tax_zone_id',
        'phone',
        'email',
        'timezone',
        'operating_hours',
        'is_virtual',
        'include_in_default_queries',
        'is_default',
        'shortage_policy',
        'shortage_dispatch_policy',
        'shortage_auto_resolve_enabled',
        'shortage_preferred_resolvers',
        'tag_list',
        'brand_logo_path',
        'brand_primary_colour',
        'brand_secondary_colour',
        'brand_font_family',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_virtual' => 'boolean',
            'include_in_default_queries' => 'boolean',
            'operating_hours' => 'array',
            'shortage_policy' => ShortagePolicy::class,
            'shortage_dispatch_policy' => ShortageDispatchPolicy::class,
            'shortage_auto_resolve_enabled' => 'boolean',
            'shortage_preferred_resolvers' => 'array',
            'tag_list' => 'array',
        ];
    }

    /**
     * The store's shortage confirmation-gate policy, falling back to the
     * framework default when the column is unset (legacy rows).
     */
    public function shortagePolicy(): ShortagePolicy
    {
        return $this->shortage_policy ?? ShortagePolicy::default();
    }

    /**
     * The store's shortage dispatch-gate policy, falling back to the framework
     * default when the column is unset (legacy rows). This is the seam the
     * dispatch gate reads to decide how short line items are handled at dispatch
     * time (shortage-resolution-sub-hires.md §7.4).
     */
    public function dispatchPolicy(): ShortageDispatchPolicy
    {
        return $this->shortage_dispatch_policy ?? ShortageDispatchPolicy::default();
    }

    /**
     * Whether the store runs the synchronous auto-resolution loop before the
     * confirmation gate evaluates (shortage-resolution-sub-hires.md §7.5).
     */
    public function autoResolvesShortages(): bool
    {
        return (bool) $this->shortage_auto_resolve_enabled;
    }

    /**
     * The ordered resolver keys the auto-resolution loop iterates. An empty list
     * means "all registered resolvers in priority order" — the caller decides the
     * fallback so the policy stays config-driven (never hardcoded keys).
     *
     * @return list<string>
     */
    public function preferredResolvers(): array
    {
        return array_values(array_filter(
            $this->shortage_preferred_resolvers ?? [],
            static fn (string $key): bool => $key !== '',
        ));
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('name')->label('Name')->required()->searchable()->filterable()->sortable();
        $builder->string('street')->label('Street');
        $builder->string('city')->label('City')->filterable()->sortable();
        $builder->string('county')->label('County')->filterable();
        $builder->string('postcode')->label('Postcode')->filterable();
        $builder->string('country_code')->label('Country Code')->filterable();
        $builder->relation('country_id')->label('Country')
            ->relation('country', 'belongsTo', Country::class, 'name')
            ->filterable();
        $builder->relation('tax_zone_id')->label('Tax Zone')
            ->relation('taxZone', 'belongsTo', TaxZone::class, 'name')
            ->group('Tax')
            ->filterable();
        $builder->string('phone')->label('Phone');
        $builder->string('email')->label('Email');
        $builder->string('timezone')->label('Timezone')->filterable();
        $builder->boolean('is_default')->label('Default Store')->filterable()->sortable();
        $builder->datetime('created_at')->label('Created')->sortable();
        $builder->datetime('updated_at')->label('Updated')->sortable();
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<TaxZone, $this>
     */
    public function taxZone(): BelongsTo
    {
        return $this->belongsTo(TaxZone::class);
    }

    /**
     * @return HasMany<DocumentTemplate, $this>
     */
    public function documentTemplates(): HasMany
    {
        return $this->hasMany(DocumentTemplate::class);
    }

    /**
     * @return HasMany<DocumentSequence, $this>
     */
    public function documentSequences(): HasMany
    {
        return $this->hasMany(DocumentSequence::class);
    }

    /** @return HasMany<Stocktake, $this> */
    public function stocktakes(): HasMany
    {
        return $this->hasMany(Stocktake::class);
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Scope to the default store.
     *
     * @param  Builder<Store>  $query
     * @return Builder<Store>
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to stores that participate in default availability queries.
     *
     * Virtual/secondary stores (vehicles, job sites, sub-hire holding locations)
     * flagged `include_in_default_queries = false` are excluded — they remain
     * addressable directly by id but never appear in store-spanning availability
     * grids unless explicitly requested.
     *
     * @param  Builder<Store>  $query
     * @return Builder<Store>
     */
    public function scopeInDefaultQueries(Builder $query): Builder
    {
        return $query->where('include_in_default_queries', true);
    }
}
