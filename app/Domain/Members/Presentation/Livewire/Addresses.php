<?php

declare(strict_types=1);

namespace App\Domain\Members\Presentation\Livewire;

use App\Domain\Members\Actions\DeleteAddress;
use App\Domain\Members\Models\Member;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Addresses extends Component
{
    public Member $member;

    public function mount(Member $member): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links']);
    }

    public function deleteAddress(int $addressId): void
    {
        $address = $this->member->addresses()->findOrFail($addressId);
        (new DeleteAddress)($address);
        $this->member->loadCount(['addresses', 'emails', 'phones', 'links']);
    }

    /** @return array<string, mixed> */
    private function viewData(): array
    {
        return [
            'addresses' => $this->member->addresses()->with(['country', 'type'])->orderBy('is_primary', 'desc')->get(),
        ];
    }

    public function render(): View
    {
        return view('members::addresses', $this->viewData());
    }
}
