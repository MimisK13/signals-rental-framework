<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Presentation\Livewire\Settings;

use App\Domain\Administration\Models\User;
use App\Domain\Notifications\Actions\SaveMemberNotificationPreferences;
use App\Domain\Notifications\Data\SaveMemberNotificationPreferencesData;
use App\Domain\Notifications\Models\NotificationType;
use App\Domain\Notifications\Services\MemberNotificationPreferenceCatalog;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Notifications')] final class Preferences extends Component
{
    /** @var array<int, array{channels: list<string>, is_muted: bool}> */
    public array $preferences = [];

    public function mount(MemberNotificationPreferenceCatalog $catalog): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        foreach ($catalog->forUser($user) as $row) {
            /** @var NotificationType $type */
            $type = $row['type'];

            $this->preferences[$type->id] = [
                'channels' => $row['member_channels'],
                'is_muted' => $row['is_muted'],
            ];
        }
    }

    public function save(MemberNotificationPreferenceCatalog $catalog): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        $allowedTypeIds = $catalog->forUser($user)
            ->map(fn (array $row): int => $row['type']->id)
            ->all();

        foreach ($this->preferences as $typeId => $state) {
            if (! in_array((int) $typeId, $allowedTypeIds, true)) {
                continue;
            }

            app(SaveMemberNotificationPreferences::class)($user, new SaveMemberNotificationPreferencesData(
                targetUserId: $user->id,
                notificationTypeId: (int) $typeId,
                channels: $state['is_muted'] ? null : $state['channels'],
                isMuted: $state['is_muted'],
            ));
        }

        $this->dispatch('toast', type: 'success', message: __('Notification preferences saved.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function with(MemberNotificationPreferenceCatalog $catalog): array
    {
        $user = Auth::user();

        return [
            'catalog' => $user instanceof User ? $catalog->forUser($user)->groupBy(fn (array $row): string => $row['type']->category) : collect(),
            'channelLabels' => [
                'in_app' => 'In-app',
                'email' => 'Email',
                'sms' => 'SMS',
                'slack' => 'Slack',
                'teams' => 'Teams',
            ],
        ];
    }

    public function render(): View
    {
        $catalog = app(MemberNotificationPreferenceCatalog::class);

        return view('notifications::settings.preferences', $this->with($catalog));
    }
}
