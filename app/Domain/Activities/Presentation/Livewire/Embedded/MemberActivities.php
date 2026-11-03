<?php

declare(strict_types=1);

namespace App\Domain\Activities\Presentation\Livewire\Embedded;

use App\Domain\Activities\Presentation\Livewire\Concerns\HasActivityActions;
use App\Domain\Members\Models\Member;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class MemberActivities extends Component
{
    use HasActivityActions;

    public Member $member;

    public function mount(Member $member): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links', 'organisations', 'contacts']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->member->name.' — Activities');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'columns' => $this->activityColumns(),
            'scopes' => [
                'forMember' => $this->member->id,
            ],
        ];
    }

    public function render(): View
    {
        return view('activities::embedded.members', $this->with());
    }
}
