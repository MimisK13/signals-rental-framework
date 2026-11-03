<?php

declare(strict_types=1);

namespace App\Domain\Products\Policies;

use App\Policies\Traits\AuthorizesByPermission;

class ProductGroupPolicy
{
    use AuthorizesByPermission;

    protected function viewPermission(): string
    {
        return 'products.view';
    }

    protected function managePermission(): string
    {
        return 'products.create';
    }

    protected function createPermission(): string
    {
        return 'products.create';
    }

    protected function editPermission(): string
    {
        return 'products.edit';
    }

    protected function deletePermission(): string
    {
        return 'products.delete';
    }
}
