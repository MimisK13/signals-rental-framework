<?php

declare(strict_types=1);

namespace App\Domain\Assets\Models;

use App\Domain\Assets\Database\Factories\AssetUsageFactory;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $asset_id
 * @property int $opportunity_item_id
 * @property Carbon $dispatched_at
 * @property Carbon|null $returned_at
 */
class AssetUsage extends Model
{
    /** @use HasFactory<AssetUsageFactory> */
    use HasFactory;

    protected static function newFactory(): AssetUsageFactory
    {
        return AssetUsageFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'asset_id',
        'opportunity_item_id',
        'dispatched_at',
        'returned_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return BelongsTo<OpportunityItem, $this>
     */
    public function opportunityItem(): BelongsTo
    {
        return $this->belongsTo(OpportunityItem::class);
    }
}
