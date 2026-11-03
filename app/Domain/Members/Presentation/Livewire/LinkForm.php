<?php

declare(strict_types=1);

namespace App\Domain\Members\Presentation\Livewire;

use App\Domain\Lists\Models\ListName;
use App\Domain\Members\Actions\CreateLink;
use App\Domain\Members\Actions\UpdateLink;
use App\Domain\Members\Data\CreateLinkData;
use App\Domain\Members\Data\UpdateLinkData;
use App\Domain\Members\Models\Link;
use App\Domain\Members\Models\Member;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class LinkForm extends Component
{
    public Member $member;

    public ?int $linkId = null;

    public string $url = '';

    public string $name = '';

    public ?int $typeId = null;

    public function mount(Member $member, ?Link $link = null): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links', 'organisations', 'contacts']);

        if ($link?->exists) {
            $this->linkId = $link->id;
            $this->url = $link->url;
            $this->name = $link->name ?? '';
            $this->typeId = $link->type_id;
        }
    }

    public function save(): void
    {
        $this->validate([
            'url' => ['required', 'url', 'max:2048'],
            'name' => ['nullable', 'string', 'max:255'],
            'typeId' => ['nullable', 'integer', 'exists:list_values,id'],
        ]);

        $data = [
            'url' => $this->url,
            'name' => $this->name ?: null,
            'type_id' => $this->typeId,
        ];

        if ($this->linkId) {
            $link = $this->member->links()->findOrFail($this->linkId);
            (new UpdateLink)($link, UpdateLinkData::from($data));
        } else {
            (new CreateLink)($this->member, CreateLinkData::from($data));
        }

        $this->redirect(route('members.information', $this->member), navigate: true);
    }

    /** @return array<string, mixed> */
    private function viewData(): array
    {
        $linkTypes = ListName::where('name', 'Link Type')->first()?->values()->where('is_active', true)->orderBy('sort_order')->get() ?? collect();

        return [
            'isEditing' => $this->linkId !== null,
            'linkTypes' => $linkTypes,
        ];
    }

    public function render(): View
    {
        return view('members::link-form', $this->viewData());
    }
}
