<?php

declare(strict_types=1);

namespace App\Domain\Members\Presentation\Livewire;

use App\Domain\CustomFields\Models\CustomField;
use App\Domain\Members\Actions\DeleteAddress;
use App\Domain\Members\Actions\DeleteEmail;
use App\Domain\Members\Actions\DeleteLink;
use App\Domain\Members\Actions\DeletePhone;
use App\Domain\Members\Models\Member;
use App\Domain\ReferenceData\Models\Country;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')] final class Information extends Component
{
    public Member $member;

    public function mount(Member $member): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links', 'organisations', 'contacts']);
        $this->member->load([
            'addresses.country', 'addresses.type',
            'emails.type', 'phones.type', 'links.type',
        ]);
    }

    public function rendering(View $view): void
    {
        $view->title($this->member->name.' — Information');
    }

    public function deleteAddress(int $addressId): void
    {
        $address = $this->member->addresses()->findOrFail($addressId);
        (new DeleteAddress)($address);
        $this->member->load(['addresses.country', 'addresses.type']);
        $this->member->loadCount(['addresses']);
    }

    public function deleteEmail(int $emailId): void
    {
        $email = $this->member->emails()->findOrFail($emailId);
        (new DeleteEmail)($email);
        $this->member->load(['emails.type']);
        $this->member->loadCount(['emails']);
    }

    public function deletePhone(int $phoneId): void
    {
        $phone = $this->member->phones()->findOrFail($phoneId);
        (new DeletePhone)($phone);
        $this->member->load(['phones.type']);
        $this->member->loadCount(['phones']);
    }

    public function deleteLink(int $linkId): void
    {
        $link = $this->member->links()->findOrFail($linkId);
        (new DeleteLink)($link);
        $this->member->load(['links.type']);
        $this->member->loadCount(['links']);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $customFields = CustomField::query()
            ->forModule('Member')
            ->active()
            ->with('group')
            ->orderBy('sort_order')
            ->get();

        $customFieldValues = $this->member->customFieldValues()
            ->with('customField')
            ->get()
            ->keyBy('custom_field_id');

        $groupedFields = $customFields->groupBy(fn ($field) => $field->group->name ?? 'General');

        $phones = $this->member->phones->sortByDesc('is_primary');
        $countryCodes = $phones->pluck('country_code')->filter()->unique()->values()->all();
        $countryPrefixes = $countryCodes
            ? Country::whereIn('code', $countryCodes)->pluck('phone_prefix', 'code')->all()
            : [];

        return [
            'addresses' => $this->member->addresses->sortByDesc('is_primary'),
            'emails' => $this->member->emails->sortByDesc('is_primary'),
            'phones' => $phones,
            'links' => $this->member->links->sortBy('name'),
            'countryPrefixes' => $countryPrefixes,
            'groupedFields' => $groupedFields,
            'customFieldValues' => $customFieldValues,
        ];
    }

    public function render(): View
    {
        return view('members::information', $this->with());
    }
}
