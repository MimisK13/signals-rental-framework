<?php

declare(strict_types=1);

namespace App\Domain\Stores\Concerns;

use App\Domain\Stores\Scopes\StoreScope;

trait HasStoreScoping
{
    public static function bootHasStoreScoping(): void
    {
        static::addGlobalScope(new StoreScope);
    }
}
