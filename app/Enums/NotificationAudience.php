<?php

namespace App\Enums;

enum NotificationAudience: string
{
    case Internal = 'internal';
    case External = 'external';
    case Both = 'both';
}
