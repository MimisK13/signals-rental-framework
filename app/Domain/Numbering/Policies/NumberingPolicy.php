<?php

declare(strict_types=1);

namespace App\Domain\Numbering\Policies;

use App\Policies\Traits\AuthorizesByPermission;

final class NumberingPolicy
{
    use AuthorizesByPermission;

    protected function viewPermission(): string
    {
        return 'numbering.view';
    }

    protected function managePermission(): string
    {
        return 'numbering.edit';
    }
}
