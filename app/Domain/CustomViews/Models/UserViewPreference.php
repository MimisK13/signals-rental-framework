<?php

declare(strict_types=1);

namespace App\Domain\CustomViews\Models;

use App\Domain\Administration\Models\User;
use App\Domain\CustomViews\Database\Factories\UserViewPreferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserViewPreference extends Model
{
    /** @use HasFactory<UserViewPreferenceFactory> */
    use HasFactory;

    protected static function newFactory(): UserViewPreferenceFactory
    {
        return UserViewPreferenceFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'entity_type',
        'custom_view_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<CustomView, $this>
     */
    public function customView(): BelongsTo
    {
        return $this->belongsTo(CustomView::class);
    }
}
