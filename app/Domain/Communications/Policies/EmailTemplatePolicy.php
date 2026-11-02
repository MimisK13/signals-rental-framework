<?php

declare(strict_types=1);

namespace App\Domain\Communications\Policies;

use App\Policies\Traits\AuthorizesByPermission;

final class EmailTemplatePolicy
{
    use AuthorizesByPermission;

    protected function managePermission(): string
    {
        return 'email-templates.manage';
    }
}
