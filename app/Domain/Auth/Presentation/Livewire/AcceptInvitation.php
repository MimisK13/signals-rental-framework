<?php

declare(strict_types=1);

namespace App\Domain\Auth\Presentation\Livewire;

use App\Domain\Administration\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('auth::components.layout')]
final class AcceptInvitation extends Component
{
    public User $user;

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(User $user): void
    {
        if ($user->invitation_accepted_at) {
            redirect()->route('login');
        }
    }

    public function accept(): void
    {
        $this->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $this->user->update([
            'password' => Hash::make($this->password),
            'invitation_accepted_at' => now(),
        ]);

        Auth::login($this->user);

        redirect()->route('dashboard');
    }

    public function render(): View
    {
        return view('auth::accept-invitation');
    }
}
