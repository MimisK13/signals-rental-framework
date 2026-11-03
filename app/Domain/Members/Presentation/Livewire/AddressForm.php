<?php

declare(strict_types=1);

namespace App\Domain\Members\Presentation\Livewire;

use App\Domain\Lists\Models\ListName;
use App\Domain\Members\Actions\CreateAddress;
use App\Domain\Members\Actions\UpdateAddress;
use App\Domain\Members\Data\CreateAddressData;
use App\Domain\Members\Data\UpdateAddressData;
use App\Domain\Members\Models\Address;
use App\Domain\Members\Models\Member;
use App\Domain\ReferenceData\Models\Country;
use App\Services\What3WordsService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class AddressForm extends Component
{
    public Member $member;

    public ?int $addressId = null;

    public string $name = '';

    public string $street = '';

    public string $city = '';

    public string $county = '';

    public string $postcode = '';

    public ?int $countryId = null;

    public ?int $typeId = null;

    public bool $isPrimary = false;

    public ?string $what3words = null;

    public ?string $latitude = null;

    public ?string $longitude = null;

    public function mount(Member $member, ?Address $address = null): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links', 'organisations', 'contacts']);

        if ($address?->exists) {
            $this->addressId = $address->id;
            $this->name = $address->name ?? '';
            $this->street = $address->street ?? '';
            $this->city = $address->city ?? '';
            $this->county = $address->county ?? '';
            $this->postcode = $address->postcode ?? '';
            $this->countryId = $address->country_id;
            $this->typeId = $address->type_id;
            $this->isPrimary = $address->is_primary;
            $this->latitude = $address->latitude ? (string) $address->latitude : null;
            $this->longitude = $address->longitude ? (string) $address->longitude : null;
        }
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:255'],
            'countryId' => ['nullable', 'integer', 'exists:countries,id'],
            'typeId' => ['nullable', 'integer', 'exists:list_values,id'],
            'isPrimary' => ['boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        DB::transaction(function () {
            if ($this->isPrimary) {
                $this->member->addresses()->update(['is_primary' => false]);
            }

            if ($this->addressId) {
                $address = $this->member->addresses()->findOrFail($this->addressId);
                (new UpdateAddress)($address, UpdateAddressData::from([
                    'street' => $this->street ?: null,
                    'name' => $this->name ?: null,
                    'city' => $this->city ?: null,
                    'county' => $this->county ?: null,
                    'postcode' => $this->postcode ?: null,
                    'country_id' => $this->countryId,
                    'type_id' => $this->typeId,
                    'is_primary' => $this->isPrimary,
                    'latitude' => $this->latitude ? (float) $this->latitude : null,
                    'longitude' => $this->longitude ? (float) $this->longitude : null,
                ]));
            } else {
                (new CreateAddress)($this->member, CreateAddressData::from([
                    'street' => $this->street,
                    'name' => $this->name ?: null,
                    'city' => $this->city ?: null,
                    'county' => $this->county ?: null,
                    'postcode' => $this->postcode ?: null,
                    'country_id' => $this->countryId,
                    'type_id' => $this->typeId,
                    'is_primary' => $this->isPrimary,
                    'latitude' => $this->latitude ? (float) $this->latitude : null,
                    'longitude' => $this->longitude ? (float) $this->longitude : null,
                ]));
            }
        });

        $this->redirect(route('members.information', $this->member), navigate: true);
    }

    public function lookupWhat3words(): void
    {
        $service = app(What3WordsService::class);

        if (! $service->isConfigured()) {
            $this->addError('what3words', 'what3words is not configured. Set it up in Integrations settings.');

            return;
        }

        $this->validate([
            'what3words' => ['required', 'string', 'regex:/^[a-zA-Z]+\.[a-zA-Z]+\.[a-zA-Z]+$/'],
        ]);

        $result = $service->convertToCoordinates($this->what3words);

        if ($result === null) {
            $this->addError('what3words', 'Could not resolve what3words address. Check the address and your API key in Settings > Integrations.');

            return;
        }

        $this->latitude = (string) $result['lat'];
        $this->longitude = (string) $result['lng'];
    }

    public function updateCoordinates(string $lat, string $lng): void
    {
        if (is_numeric($lat) && is_numeric($lng)) {
            $this->latitude = $lat;
            $this->longitude = $lng;
        }
    }

    public function geocodeFromAddress(): void
    {
        $parts = array_filter([
            $this->street,
            $this->city,
            $this->county,
            $this->postcode,
            $this->countryId ? Country::find($this->countryId)?->name : null,
        ]);

        if (empty($parts)) {
            $this->addError('latitude', 'Enter an address before geocoding.');

            return;
        }

        $service = app(What3WordsService::class);
        $coords = $service->geocodeAddress(implode(', ', $parts));

        if ($coords === null) {
            $this->addError('latitude', 'Could not geocode this address. Try adding more detail.');

            return;
        }

        $this->latitude = (string) $coords['lat'];
        $this->longitude = (string) $coords['lng'];
    }

    /** @return array<string, mixed> */
    private function viewData(): array
    {
        $addressTypes = ListName::where('name', 'Address Type')->first()?->values()->where('is_active', true)->orderBy('sort_order')->get() ?? collect();

        return [
            'isEditing' => $this->addressId !== null,
            'countries' => Country::query()->active()->orderBy('name')->get(),
            'addressTypes' => $addressTypes,
            'what3wordsConfigured' => app(What3WordsService::class)->isConfigured(),
            'canManageIntegrations' => auth()->user()?->hasAdminAccess() ?? false,
        ];
    }

    public function render(): View
    {
        return view('members::address-form', $this->viewData());
    }
}
