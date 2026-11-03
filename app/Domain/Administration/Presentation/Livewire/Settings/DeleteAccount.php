<?php

declare(strict_types=1);

namespace App\Domain\Administration\Presentation\Livewire\Settings;

use App\Domain\Administration\Actions\DeleteOwnAccount;
use App\Domain\Auth\Presentation\Actions\Logout;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class DeleteAccount extends Component
{
    public string $password = '';

    /**
     * Whether the authenticated user is allowed to delete their own account.
     */
    public function canDeleteSelf(): bool
    {
        return $this->selfDeleteBlockReason() === null;
    }

    /**
     * The reason the user cannot self-delete, or null if they can.
     */
    public function selfDeleteBlockReason(): ?string
    {
        return app(DeleteOwnAccount::class)->blockReason(Auth::user());
    }

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        if ($reason = $this->selfDeleteBlockReason()) {
            $this->addError('password', $reason);

            return;
        }

        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        try {
            app(DeleteOwnAccount::class)->__invoke(Auth::user(), function () use ($logout): void {
                $logout();
            });
        } catch (ValidationException $exception) {
            $this->addError('password', $exception->errors()['account'][0] ?? 'Your account cannot be deleted.');

            return;
        }

        $this->redirect('/', navigate: true);
    }

    public function render(): View
    {
        return view('administration::settings.delete-account');
    }
}
