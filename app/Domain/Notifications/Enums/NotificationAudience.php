<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Enums;

enum NotificationAudience: string
{
    case Internal = 'internal';
    case External = 'external';
    case Both = 'both';
}
