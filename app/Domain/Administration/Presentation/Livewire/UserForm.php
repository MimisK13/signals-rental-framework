<?php

declare(strict_types=1);

namespace App\Domain\Administration\Presentation\Livewire;

use App\Domain\Administration\Actions\DeactivateUser;
use App\Domain\Administration\Actions\DeleteUser;
use App\Domain\Administration\Actions\ReactivateUser;
use App\Domain\Administration\Actions\SendPasswordReset;
use App\Domain\Administration\Actions\UpdateUser;
use App\Domain\Administration\Models\User;
use App\Domain\Notifications\Notifications\UserInvitedNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('components.layouts.app')]
#[Title('User')]
final class UserForm extends Component
{
    public int $userId;

    public string $userName = '';

    public string $userEmail = '';

    /** @var list<string> */
    public array $selectedRoles = [];

    public bool $isOwner = false;

    public bool $isDeactivated = false;

    public bool $isInvited = false;

    public bool $hasPassword = false;

    public ?int $confirmingDeactivation = null;

    public ?int $confirmingDeletion = null;

    public function mount(User $user): void
    {
        Gate::authorize('users.edit');

        $this->userId = $user->id;
        $this->userName = $user->name;
        $this->userEmail = $user->email;
        $this->selectedRoles = $user->roles->pluck('name')->all();
        $this->isOwner = $user->isOwner();
        $this->isDeactivated = ! $user->isActive();
        $this->isInvited = $user->invited_at && ! $user->invitation_accepted_at;
        $this->hasPassword = (bool) $user->password;
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'availableRoles' => Role::query()
                ->orderBy('sort_order')
                ->get(),
            'isEditing' => true,
        ];
    }

    public function save(): void
    {
        $this->validate([
            'userName' => ['required', 'string', 'max:255'],
            'userEmail' => ['required', 'email', 'max:255', 'unique:users,email,'.$this->userId],
            'selectedRoles' => ['array'],
            'selectedRoles.*' => ['string', 'exists:roles,name'],
        ]);

        $user = User::findOrFail($this->userId);

        (new UpdateUser)($user, [
            'name' => $this->userName,
            'email' => $this->userEmail,
            'roles' => $this->selectedRoles,
        ]);

        $this->redirect(route('admin.settings.users'), navigate: true);
    }

    public function deactivate(): void
    {
        $user = User::findOrFail($this->userId);
        (new DeactivateUser)($user);
        $this->confirmingDeactivation = null;
        $this->isDeactivated = true;
        $this->dispatch('user-deactivated');
    }

    public function reactivate(): void
    {
        $user = User::findOrFail($this->userId);
        (new ReactivateUser)($user);
        $this->isDeactivated = false;
        $this->dispatch('user-reactivated');
    }

    public function sendPasswordReset(): void
    {
        $user = User::findOrFail($this->userId);
        (new SendPasswordReset)($user);
        $this->dispatch('password-reset-sent');
    }

    public function resendInvitation(): void
    {
        Gate::authorize('users.invite');

        $user = User::findOrFail($this->userId);

        if (! $user->invited_at || $user->invitation_accepted_at) {
            throw ValidationException::withMessages([
                'user' => 'This user does not have a pending invitation.',
            ]);
        }

        $user->notify(new UserInvitedNotification);
        $this->dispatch('invitation-resent');
    }

    public function deleteUser(): void
    {
        $user = User::findOrFail($this->userId);
        (new DeleteUser)($user);
        $this->redirect(route('admin.settings.users'), navigate: true);
    }

    public function render(): View
    {
        return view('administration::user-form', $this->with());
    }
}
