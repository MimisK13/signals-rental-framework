<?php

declare(strict_types=1);

namespace App\Livewire\Members;

use App\Actions\Members\CreatePhone;
use App\Actions\Members\UpdatePhone;
use App\Data\Members\CreatePhoneData;
use App\Data\Members\UpdatePhoneData;
use App\Domain\Lists\Models\ListName;
use App\Models\Country;
use App\Models\Member;
use App\Models\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class PhoneForm extends Component
{
    public Member $member;

    public ?int $phoneId = null;

    public string $number = '';

    public ?string $countryCode = null;

    public ?int $typeId = null;

    public bool $isPrimary = false;

    public function mount(Member $member, ?Phone $phone = null): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links', 'organisations', 'contacts']);

        if ($phone?->exists) {
            $this->phoneId = $phone->id;
            $this->number = $phone->number;
            $this->countryCode = $phone->country_code;
            $this->typeId = $phone->type_id;
            $this->isPrimary = $phone->is_primary;
        }
    }

    public function save(): void
    {
        $this->validate([
            'number' => ['required', 'string', 'max:50'],
            'countryCode' => ['nullable', 'string', 'size:2'],
            'typeId' => ['nullable', 'integer', 'exists:list_values,id'],
            'isPrimary' => ['boolean'],
        ]);

        $data = [
            'number' => $this->number,
            'country_code' => $this->countryCode,
            'type_id' => $this->typeId,
            'is_primary' => $this->isPrimary,
        ];

        DB::transaction(function () use ($data) {
            if ($this->isPrimary) {
                $this->member->phones()->update(['is_primary' => false]);
            }

            if ($this->phoneId) {
                $phone = $this->member->phones()->findOrFail($this->phoneId);
                (new UpdatePhone)($phone, UpdatePhoneData::from($data));
            } else {
                (new CreatePhone)($this->member, CreatePhoneData::from($data));
            }
        });

        $this->redirect(route('members.information', $this->member), navigate: true);
    }

    /** @return array<string, mixed> */
    private function viewData(): array
    {
        $phoneTypes = ListName::where('name', 'Phone Type')->first()?->values()->where('is_active', true)->orderBy('sort_order')->get() ?? collect();

        $countries = Country::query()->active()->orderBy('name')->get(['id', 'name', 'code', 'phone_prefix'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'iso2' => $c->code, 'phone_prefix' => $c->phone_prefix])
            ->toArray();

        return [
            'isEditing' => $this->phoneId !== null,
            'phoneTypes' => $phoneTypes,
            'countries' => $countries,
        ];
    }

    public function render(): View
    {
        return view('livewire.members.phone-form', $this->viewData());
    }
}
