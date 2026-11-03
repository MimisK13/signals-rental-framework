<?php

declare(strict_types=1);

namespace App\Domain\Settings\Models;

use App\Domain\Settings\Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $group
 * @property string $key
 * @property string|null $value
 * @property string $type
 */
final class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
    ];

    protected static function newFactory(): SettingFactory
    {
        return SettingFactory::new();
    }

    /**
     * Scope to filter settings by group.
     *
     * @param  Builder<Setting>  $query
     * @return Builder<Setting>
     */
    public function scopeForGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    /**
     * Scope to filter settings by key within a group.
     *
     * @param  Builder<Setting>  $query
     * @return Builder<Setting>
     */
    public function scopeForKey(Builder $query, string $group, string $key): Builder
    {
        return $query->where('group', $group)->where('key', $key);
    }
}
