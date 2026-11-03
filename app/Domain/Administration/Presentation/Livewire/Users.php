<?php

declare(strict_types=1);

namespace App\Domain\Administration\Presentation\Livewire;

use App\Domain\Administration\Actions\DeactivateUser;
use App\Domain\Administration\Actions\InviteUser;
use App\Domain\Administration\Actions\ReactivateUser;
use App\Domain\Administration\Actions\SendPasswordReset;
use App\Domain\Administration\Actions\TransferOwnership;
use App\Domain\Administration\Data\InviteUserData;
use App\Domain\Administration\Models\User;
use App\Domain\Notifications\Notifications\UserInvitedNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('components.layouts.app')]
#[Title('Users')]
final class Users extends Component
{
    // Invite modal
    public bool $showInviteModal = false;

    public string $inviteName = '';

    public string $inviteEmail = '';

    /** @var list<string> */
    public array $inviteRoles = [];

    // Confirmation
    public ?int $confirmingDeactivation = null;

    public ?int $confirmingTransfer = null;

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'users' => User::query()
                ->with('roles')
                ->orderByDesc('is_owner')
                ->orderByDesc('is_admin')
                ->orderBy('name')
                ->get(),
            'availableRoles' => Role::query()
                ->where('name', '!=', 'Owner')
                ->orderBy('sort_order')
                ->get(),
        ];
    }

    public function openInviteModal(): void
    {
        if (Role::query()->count() === 0) {
            $this->addError('invite', 'Please create at least one role before inviting users.');

            return;
        }

        $this->reset('inviteName', 'inviteEmail', 'inviteRoles');
        $this->showInviteModal = true;
    }

    public function invite(): void
    {
        $this->validate([
            'inviteName' => ['required', 'string', 'max:255'],
            'inviteEmail' => ['required', 'email', 'max:255', 'unique:users,email'],
            'inviteRoles' => ['required', 'array', 'min:1'],
            'inviteRoles.*' => ['string', 'exists:roles,name'],
        ]);

        $data = InviteUserData::from([
            'name' => $this->inviteName,
            'email' => $this->inviteEmail,
            'roles' => $this->inviteRoles,
        ]);

        (new InviteUser)($data);

        $this->showInviteModal = false;
        $this->dispatch('user-invited');
    }

    public function deactivate(int $userId): void
    {
        $user = User::findOrFail($userId);
        (new DeactivateUser)($user);
        $this->confirmingDeactivation = null;
        $this->dispatch('user-deactivated');
    }

    public function reactivate(int $userId): void
    {
        $user = User::findOrFail($userId);
        (new ReactivateUser)($user);
        $this->dispatch('user-reactivated');
    }

    public function sendPasswordReset(int $userId): void
    {
        $user = User::findOrFail($userId);
        (new SendPasswordReset)($user);
        $this->dispatch('password-reset-sent');
    }

    public function resendInvitation(int $userId): void
    {
        Gate::authorize('users.invite');

        $user = User::findOrFail($userId);

        if (! $user->invited_at || $user->invitation_accepted_at) {
            $this->addError('user', 'This user was not invited or has already accepted.');

            return;
        }

        $user->notify(new UserInvitedNotification);
        $this->dispatch('invitation-resent');
    }

    public function transferOwnership(int $userId): void
    {
        $user = User::findOrFail($userId);
        (new TransferOwnership)($user);
        $this->confirmingTransfer = null;
        $this->dispatch('ownership-transferred');
    }

    public function render(): View
    {
        return view('administration::users', $this->with());
    }
}
