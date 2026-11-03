<?php

declare(strict_types=1);

namespace App\Domain\Stock\Policies;

use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stores\Policies\Concerns\ChecksStoreAccess;
use App\Models\User;
use App\Policies\Traits\AuthorizesByPermission;

class StockLevelPolicy
{
    use AuthorizesByPermission, ChecksStoreAccess;

    public function access(User $user, StockLevel $stockLevel): bool
    {
        return $this->canAccessStore($user, $stockLevel);
    }

    protected function viewPermission(): string
    {
        return 'stock.view';
    }

    protected function managePermission(): string
    {
        return 'stock.adjust';
    }
}
