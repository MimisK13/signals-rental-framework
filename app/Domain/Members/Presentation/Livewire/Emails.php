<?php

declare(strict_types=1);

namespace App\Domain\Members\Presentation\Livewire;

use App\Domain\Members\Actions\DeleteEmail;
use App\Domain\Members\Models\Member;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Emails extends Component
{
    public Member $member;

    public function mount(Member $member): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links']);
    }

    public function deleteEmail(int $emailId): void
    {
        $email = $this->member->emails()->findOrFail($emailId);
        (new DeleteEmail)($email);
        $this->member->loadCount(['addresses', 'emails', 'phones', 'links']);
    }

    /** @return array<string, mixed> */
    private function viewData(): array
    {
        return [
            'emails' => $this->member->emails()->with('type')->orderBy('is_primary', 'desc')->get(),
        ];
    }

    public function render(): View
    {
        return view('members::emails', $this->viewData());
    }
}
