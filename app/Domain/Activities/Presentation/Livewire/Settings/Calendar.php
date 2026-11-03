<?php

declare(strict_types=1);

namespace App\Domain\Activities\Presentation\Livewire\Settings;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Calendar')]
final class Calendar extends Component
{
    /**
     * The current user's permanent, signed iCal subscribe URL.
     */
    #[Computed]
    public function feedUrl(): string
    {
        return URL::signedRoute('calendar.feed.user', ['user' => Auth::id()]);
    }

    /**
     * The signed global iCal feed URL (shown to admins/owners only).
     */
    #[Computed]
    public function globalFeedUrl(): string
    {
        return URL::signedRoute('calendar.feed.global');
    }

    /**
     * Whether the current user may see the system-wide global feed.
     */
    #[Computed]
    public function canSeeGlobalFeed(): bool
    {
        $user = Auth::user();

        return $user->is_admin || $user->is_owner;
    }

    public function render(): View
    {
        return view('activities::settings.calendar');
    }
}
