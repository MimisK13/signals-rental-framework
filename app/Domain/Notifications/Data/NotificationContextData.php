<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Data;

use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Models\NotificationType;
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
