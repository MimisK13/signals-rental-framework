<?php

declare(strict_types=1);

namespace App\Domain\Currency\Policies;

use App\Policies\Traits\AuthorizesByPermission;

final class ExchangeRatePolicy
{
    use AuthorizesByPermission;

    protected function managePermission(): string
    {
        return 'settings.manage';
    }
}
