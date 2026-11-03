<?php

declare(strict_types=1);

namespace App\Domain\Stores\Policies;

use App\Policies\Traits\AuthorizesByPermission;

class StorePolicy
{
    use AuthorizesByPermission;

    protected function viewPermission(): string
    {
        return 'settings.view';
    }

    protected function managePermission(): string
    {
        return 'settings.manage';
    }
}
