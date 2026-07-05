<?php

namespace App\Services\Notifications;

use App\Enums\NotificationAudience;
use App\Models\NotificationPreference;
use App\Models\NotificationType;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds the member-facing notification preference matrix (P29 layer 3 UI).
 *
 * Toggleable channels are limited to tenant-enabled channels; members may only
 * narrow further, never enable channels the tenant has disabled.
 */
class MemberNotificationPreferenceCatalog
{
    public function __construct(
        private readonly NotificationChannelResolver $channelResolver,
    ) {}

    /**
     * @return Collection<int, array{
     *     type: NotificationType,
     *     tenant_channels: non-empty-list<string>,
     *     member_channels: list<string>,
     *     is_muted: bool
     * }>
     */
    public function forUser(User $user): Collection
    {
        $types = NotificationType::query()
            ->with('setting')
            ->where('is_active', true)
            ->whereIn('audience', [NotificationAudience::Internal, NotificationAudience::Both])
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $preferences = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->whereIn('notification_type_id', $types->pluck('id'))
            ->get()
            ->keyBy('notification_type_id');

        return $types->map(function (NotificationType $type) use ($preferences): array {
            $tenantChannels = $this->tenantEnabledChannels($type);
            /** @var NotificationPreference|null $preference */
            $preference = $preferences->get($type->id);

            $memberChannels = $tenantChannels;

            if ($preference?->channels !== null) {
                $memberChannels = array_values(array_intersect(
                    $this->normalizeChannels($preference->channels),
                    $tenantChannels,
                ));
            }

            return [
                'type' => $type,
                'tenant_channels' => $tenantChannels,
                'member_channels' => $memberChannels,
                'is_muted' => $preference !== null && $preference->is_muted,
            ];
        })->filter(fn (array $row): bool => $row['tenant_channels'] !== []);
    }

    /**
     * Tenant-enabled logical channels for a type (layer 1 ∩ layer 2).
     *
     * @return list<string>
     */
    public function tenantEnabledChannels(NotificationType $type): array
    {
        return $this->channelResolver->resolve($type);
    }

    /**
     * @param  list<string>  $channels
     * @return list<string>
     */
    private function normalizeChannels(array $channels): array
    {
        $normalized = [];

        foreach ($channels as $channel) {
            $normalized[] = match ($channel) {
                'database', 'broadcast' => 'in_app',
                'mail' => 'email',
                default => $channel,
            };
        }

        return array_values(array_unique($normalized));
    }
}
