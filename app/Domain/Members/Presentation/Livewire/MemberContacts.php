<?php

declare(strict_types=1);

namespace App\Domain\Members\Presentation\Livewire;

use App\Domain\Members\Enums\MembershipType;
use App\Domain\Members\Models\Member;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')] final class MemberContacts extends Component
{
    public Member $member;

    public function mount(Member $member): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links', 'organisations', 'contacts']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->member->name.' — Contacts');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $isOrganisation = $this->member->membership_type === MembershipType::Organisation
            || $this->member->membership_type === MembershipType::Venue;

        $typeOptions = collect(MembershipType::cases())
            ->mapWithKeys(fn (MembershipType $t): array => [$t->value => $t->label()])
            ->all();

        $columns = [
            ['key' => 'checkbox', 'type' => 'checkbox'],
            ['key' => 'avatar', 'label' => '', 'view' => 'members::partials.column-avatar'],
            ['key' => 'name', 'label' => 'Name', 'sortable' => true, 'filterable' => true, 'filter_type' => 'text', 'view' => 'members::partials.column-name'],
            ['key' => 'membership_type', 'label' => 'Type', 'sortable' => true, 'filterable' => true, 'filter_type' => 'select', 'filter_options' => $typeOptions, 'view' => 'members::partials.column-type'],
            ['key' => 'email', 'label' => 'Primary Email', 'view' => 'members::partials.column-email'],
            ['key' => 'phone', 'label' => 'Primary Phone', 'view' => 'members::partials.column-phone'],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'filterable' => true, 'filter_type' => 'select', 'filter_options' => ['1' => 'Active', '0' => 'Inactive'], 'view' => 'livewire.partials.column-active-status'],
            ['key' => 'actions', 'type' => 'actions'],
        ];

        // Bidirectional: show every related member regardless of how the
        // relationship was created (org→contact or contact→org).
        $scopes = ['relatedTo' => $this->member->id];

        $label = $isOrganisation ? 'contacts' : 'organisations';

        return [
            'columns' => $columns,
            'scopes' => $scopes,
            'isOrganisation' => $isOrganisation,
            'label' => $label,
        ];
    }

    public function render(): View
    {
        return view('members::member-contacts', $this->with());
    }
}
