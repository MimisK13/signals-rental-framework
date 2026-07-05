<?php

namespace App\Models;

use Database\Factories\ChannelProviderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property array<string, mixed>|null $config
 * @property Carbon|null $last_tested_at
 */
class ChannelProvider extends Model
{
    /** @use HasFactory<ChannelProviderFactory> */
    use HasFactory;

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
