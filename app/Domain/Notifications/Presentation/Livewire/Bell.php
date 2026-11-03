<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Presentation\Livewire;

use App\Domain\Administration\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

final class Bell extends Component
{
    public int $unreadCount = 0;

    public ?int $memberId = null;

    public function mount(): void
    {
        $user = Auth::user();

        $this->memberId = $user instanceof User ? $user->member_id : null;
        $this->refreshCount();
    }

    #[On('echo-private:member.{memberId},.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated')]
    public function onBroadcastNotificationCreated(): void
    {
        $this->refreshCount();
    }

    #[On('notifications-count-stale')]
    public function onNotificationsCountStale(): void
    {
        $this->refreshCount();
    }

    public function refreshCount(): void
    {
        $user = Auth::user();

        if (! $user instanceof User || $user->member === null) {
            $previousCount = $this->unreadCount;
            $this->unreadCount = 0;

            if ($previousCount !== 0) {
                $this->dispatch('notifications-stale');
            }

            return;
        }

        $previousCount = $this->unreadCount;
        $this->unreadCount = $user->member->unreadNotifications()->count();

        if ($this->unreadCount !== $previousCount) {
            $this->dispatch('notifications-stale');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [];
    }

    public function render(): View
    {
        return view('notifications::bell', $this->with());
    }
}
