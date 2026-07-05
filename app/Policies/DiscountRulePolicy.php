<?php

namespace App\Policies;

use App\Policies\Traits\AuthorizesByPermission;

class DiscountRulePolicy
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
