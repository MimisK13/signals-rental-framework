<?php

namespace App\Data\Notifications;

use App\Models\Member;
use App\Models\NotificationType;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;

/**
 * Context passed to recipient resolvers when resolving notification targets.
 */
class NotificationContextData extends Data
{
    public function __construct(
        public NotificationType $type,
        public Model $subject,
        public ?Member $actor = null,
    ) {}
}
