<?php

declare(strict_types=1);

namespace App\Domain\Auth\Models;

use App\Domain\Administration\Models\User;
use App\Domain\Auth\Database\Factories\OAuthIdentityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OAuthIdentity extends Model
{
    /** @use HasFactory<OAuthIdentityFactory> */
    use HasFactory;

    protected $table = 'oauth_identities';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'email',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory(): OAuthIdentityFactory
    {
        return OAuthIdentityFactory::new();
    }
}
