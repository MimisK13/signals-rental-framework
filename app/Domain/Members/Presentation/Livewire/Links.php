<?php

declare(strict_types=1);

namespace App\Domain\Members\Presentation\Livewire;

use App\Domain\Members\Actions\DeleteLink;
use App\Domain\Members\Models\Member;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Links extends Component
{
    public Member $member;

    public function mount(Member $member): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links']);
    }

    public function deleteLink(int $linkId): void
    {
        $link = $this->member->links()->findOrFail($linkId);
        (new DeleteLink)($link);
        $this->member->loadCount(['addresses', 'emails', 'phones', 'links']);
    }

    /** @return array<string, mixed> */
    private function viewData(): array
    {
        return [
            'links' => $this->member->links()->with('type')->orderBy('name')->get(),
        ];
    }

    public function render(): View
    {
        return view('members::links', $this->viewData());
    }
}
