<?php

declare(strict_types=1);

namespace App\Domain\Attachments\Presentation\Livewire;

use App\Domain\Attachments\Presentation\Concerns\HasFileActions;
use App\Models\Member;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class MemberFiles extends Component
{
    use HasFileActions;

    public Member $member;

    public ?int $deleteAttachmentId = null;

    public function mount(Member $member): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links', 'organisations', 'contacts', 'attachments']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->member->name.' — Files');
    }

    protected function getFileableModel(): Member
    {
        return $this->member;
    }

    public function render(): View
    {
        return view('attachments::member-files', $this->fileData());
    }
}
