<?php

namespace App\Enums;

enum MeteringChannel: string
{
    case Api = 'api';
    case WebhookSent = 'webhook_sent';
    case WebhookReceived = 'webhook_received';
}
