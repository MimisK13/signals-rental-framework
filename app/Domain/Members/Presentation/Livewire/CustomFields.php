<?php

declare(strict_types=1);

namespace App\Domain\Members\Presentation\Livewire;

use App\Domain\CustomFields\Models\CustomField;
use App\Domain\Members\Models\Member;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')] final class CustomFields extends Component
{
    public Member $member;

    public function mount(Member $member): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links', 'organisations', 'contacts']);
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $fields = CustomField::query()
            ->forModule('Member')
            ->active()
            ->with('group')
            ->orderBy('sort_order')
            ->get();

        $values = $this->member->customFieldValues()
            ->with('customField')
            ->get()
            ->keyBy('custom_field_id');

        $grouped = $fields->groupBy(fn ($field) => $field->group->name ?? 'General');

        return [
            'grouped' => $grouped,
            'values' => $values,
        ];
    }

    public function render(): View
    {
        return view('members::custom-fields', $this->with());
    }
}
