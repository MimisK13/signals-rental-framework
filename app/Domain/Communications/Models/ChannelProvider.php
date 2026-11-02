<?php

declare(strict_types=1);

namespace App\Domain\Communications\Models;

use App\Domain\Communications\Database\Factories\ChannelProviderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $channel
 * @property string $driver
 * @property string $name
 * @property array<string, mixed>|null $config
 * @property bool $is_default
 * @property bool $is_active
 * @property string|null $last_test_status
 * @property string|null $last_test_message
 * @property Carbon|null $last_tested_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class ChannelProvider extends Model
{
    /** @use HasFactory<ChannelProviderFactory> */
    use HasFactory;

    protected static function newFactory(): ChannelProviderFactory
    {
        return ChannelProviderFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'channel',
        'driver',
        'name',
        'config',
        'is_default',
        'is_active',
        'last_test_status',
        'last_test_message',
        'last_tested_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'encrypted:array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }
}
