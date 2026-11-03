<?php

declare(strict_types=1);

namespace App\Domain\Administration\Presentation\Livewire\Settings;

use App\Domain\Administration\Models\User;
use App\Domain\Members\Enums\MembershipType;
use App\Domain\Members\Models\Member;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Profile')]
final class Profile extends Component
{
    public string $name = '';

    public string $email = '';

    public Member $member;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $user = Auth::user();

        $this->name = $user->name;
        $this->email = $user->email;
        $this->member = $this->resolveMember($user);
    }

    /**
     * Resolve the authenticated user's linked member record, creating a
     * User-type member if one does not yet exist (legacy member_id gap).
     */
    private function resolveMember(User $user): Member
    {
        if ($user->member) {
            return $user->member;
        }

        $member = Member::create([
            'name' => $user->name,
            'membership_type' => MembershipType::User,
            'is_active' => $user->is_active,
        ]);

        $user->update(['member_id' => $member->id]);

        return $member;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        // Keep the linked member record's name in sync. User-type members are
        // managed from this profile page, so the user's name is authoritative.
        $user->syncMemberName();

        // The user's name drives the header avatar initials (rendered in the
        // global chrome, outside this component). Force a full reload so the
        // header updates; the flashed status survives the redirect.
        session()->flash('status', 'Profile saved.');

        $this->redirect(route('settings.profile'), navigate: false);
    }

    /**
     * Handle the embedded IconUpload component reporting a profile-photo change.
     *
     * The header avatar is rendered in the global chrome and a Livewire morph
     * cannot update it, so we force a full reload. This listener lives on the
     * profile page only — the shared IconUpload component does not reload, so
     * member/product edit pages are unaffected.
     */
    #[On('icon-updated')]
    public function handleProfilePhotoUpdated(): void
    {
        $this->redirect(route('settings.profile'), navigate: false);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    public function render(): View
    {
        return view('administration::settings.profile');
    }
}
