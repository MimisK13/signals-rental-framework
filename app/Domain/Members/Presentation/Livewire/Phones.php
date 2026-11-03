<?php

declare(strict_types=1);

namespace App\Domain\Members\Presentation\Livewire;

use App\Domain\Members\Actions\DeletePhone;
use App\Domain\Members\Models\Member;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Phones extends Component
{
    public Member $member;

    public function mount(Member $member): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links']);
    }

    public function deletePhone(int $phoneId): void
    {
        $phone = $this->member->phones()->findOrFail($phoneId);
        (new DeletePhone)($phone);
        $this->member->loadCount(['addresses', 'emails', 'phones', 'links']);
    }

    /** @return array<string, mixed> */
    private function viewData(): array
    {
        return [
            'phones' => $this->member->phones()->with('type')->orderBy('is_primary', 'desc')->get(),
        ];
    }

    public function render(): View
    {
        return view('members::phones', $this->viewData());
    }
}
