<?php

declare(strict_types=1);

namespace App\Domain\Webhooks\Policies;

use App\Policies\Traits\AuthorizesByPermission;

class WebhookPolicy
{
    use AuthorizesByPermission;

    protected function managePermission(): string
    {
        return 'webhooks.manage';
    }
}
