<?php

declare(strict_types=1);

namespace App\Domain\Lists\Policies;

use App\Policies\Traits\AuthorizesByPermission;

final class ListNamePolicy
{
    use AuthorizesByPermission;

    protected function viewPermission(): string
    {
        return 'list-values.view';
    }

    protected function managePermission(): string
    {
        return 'list-values.manage';
    }
}
