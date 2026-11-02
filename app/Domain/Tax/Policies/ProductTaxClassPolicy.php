<?php

declare(strict_types=1);

namespace App\Domain\Tax\Policies;

use App\Policies\Traits\AuthorizesByPermission;

final class ProductTaxClassPolicy
{
    use AuthorizesByPermission;

    protected function viewPermission(): string
    {
        return 'tax-classes.view';
    }

    protected function managePermission(): string
    {
        return 'tax-classes.manage';
    }
}
