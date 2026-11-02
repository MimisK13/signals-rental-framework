<?php

declare(strict_types=1);

namespace App\Livewire\Members;

use App\Actions\Members\DeleteLink;
use App\Models\Member;
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
        return view('livewire.members.links', $this->viewData());
    }
}
