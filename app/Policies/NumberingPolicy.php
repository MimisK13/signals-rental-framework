<?php

namespace App\Policies;

use App\Policies\Traits\AuthorizesByPermission;

class NumberingPolicy
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
