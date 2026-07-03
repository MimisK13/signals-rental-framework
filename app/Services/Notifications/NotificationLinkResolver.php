<?php

namespace App\Services\Notifications;

use App\Models\Member;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;

class NotificationLinkResolver
{
    public function resolve(DatabaseNotification $notification): ?string
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;

        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];

        if (isset($payload['opportunity']['id'])) {
            return route('opportunities.show', (int) $payload['opportunity']['id']);
        }

        if (isset($payload['action_url']) && is_string($payload['action_url'])) {
            return $payload['action_url'];
        }

        $subjectType = $payload['subject_type'] ?? null;
        $subjectId = $payload['subject_id'] ?? null;

        if (! is_string($subjectType) || ! is_numeric($subjectId)) {
            return null;
        }

        if (! class_exists($subjectType)) {
            return null;
        }

        /** @var Model|null $subject */
        $subject = $subjectType::query()->find((int) $subjectId);

        if ($subject === null) {
            return null;
        }

        return $this->routeForSubject($subject);
    }

    public function routeForSubject(Model $subject): ?string
    {
        return match ($subject::class) {
            Opportunity::class => route('opportunities.show', $subject),
            Member::class => route('members.show', $subject),
            default => null,
        };
    }
}
