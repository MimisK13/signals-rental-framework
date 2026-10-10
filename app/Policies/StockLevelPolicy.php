<?php

namespace App\Policies;

use App\Models\StockLevel;
use App\Models\User;
use App\Policies\Traits\AuthorizesByPermission;
use App\Policies\Traits\ChecksStoreAccess;

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
