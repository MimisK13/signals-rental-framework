<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Policies;

use App\Policies\Traits\AuthorizesByPermission;

class SurchargePolicy
{
    use AuthorizesByPermission;

    protected function viewPermission(): string
    {
        return 'pricing.view';
    }

    protected function managePermission(): string
    {
        return 'pricing.manage';
    }
}
