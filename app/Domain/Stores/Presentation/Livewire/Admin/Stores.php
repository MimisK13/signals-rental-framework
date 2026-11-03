<?php

declare(strict_types=1);

namespace App\Domain\Stores\Presentation\Livewire\Admin;

use App\Domain\ReferenceData\Data\CountryData;
use App\Domain\Stores\Actions\CreateStore;
use App\Domain\Stores\Actions\DeleteStore;
use App\Domain\Stores\Actions\UpdateStore;
use App\Domain\Stores\Data\CreateStoreData;
use App\Domain\Stores\Data\UpdateStoreData;
use App\Domain\Stores\Models\Store;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Stores')] final class Stores extends Component
{
    /** @var Collection<int, Store> */
    public $stores;

    public ?int $editingStoreId = null;

    public string $storeName = '';

    public string $storeStreet = '';

    public string $storeCity = '';

    public string $storeCounty = '';

    public string $storePostcode = '';

    public string $storeCountryCode = '';

    public function mount(): void
    {
        $this->loadStores();
    }

    public function loadStores(): void
    {
        $this->stores = Store::query()->orderBy('name')->get();
    }

    public function openCreateModal(): void
    {
        $this->resetStoreForm();
        $this->editingStoreId = null;
        $this->storeCountryCode = (string) settings('company.country_code', '');
        $this->dispatch('open-store-modal');
    }

    public function openEditModal(int $storeId): void
    {
        $store = Store::findOrFail($storeId);
        $this->editingStoreId = $store->id;
        $this->storeName = $store->name;
        $this->storeStreet = $store->street ?? '';
        $this->storeCity = $store->city ?? '';
        $this->storeCounty = $store->county ?? '';
        $this->storePostcode = $store->postcode ?? '';
        $this->storeCountryCode = $store->country_code ?? '';
        $this->dispatch('open-store-modal');
    }

    public function saveStore(): void
    {
        $validated = $this->validate([
            'storeName' => ['required', 'string', 'max:255'],
            'storeStreet' => ['nullable', 'string', 'max:255'],
            'storeCity' => ['nullable', 'string', 'max:255'],
            'storeCounty' => ['nullable', 'string', 'max:255'],
            'storePostcode' => ['nullable', 'string', 'max:20'],
            'storeCountryCode' => ['required', 'string', 'size:2'],
        ]);

        $attributes = [
            'name' => $validated['storeName'],
            'street' => $validated['storeStreet'],
            'city' => $validated['storeCity'],
            'county' => $validated['storeCounty'],
            'postcode' => $validated['storePostcode'],
            'country_code' => $validated['storeCountryCode'],
        ];

        if ($this->editingStoreId) {
            (new UpdateStore)(Store::findOrFail($this->editingStoreId), UpdateStoreData::from($attributes));
        } else {
            (new CreateStore)(CreateStoreData::from($attributes));
        }

        $this->dispatch('close-store-modal');
        $this->resetStoreForm();
        $this->loadStores();
    }

    public function setDefault(int $storeId): void
    {
        $store = Store::findOrFail($storeId);

        DB::transaction(function () use ($store): void {
            Store::query()->whereKeyNot($store->id)->update(['is_default' => false]);
            (new UpdateStore)($store, UpdateStoreData::from(['is_default' => true]));
        });

        $this->loadStores();
    }

    public function deleteStore(int $storeId): void
    {
        $store = Store::findOrFail($storeId);

        try {
            (new DeleteStore)($store);
        } catch (ValidationException $e) {
            $this->addError('deleteStore', $e->validator->errors()->first());

            return;
        }

        $this->loadStores();
    }

    private function resetStoreForm(): void
    {
        $this->storeName = '';
        $this->storeStreet = '';
        $this->storeCity = '';
        $this->storeCounty = '';
        $this->storePostcode = '';
        $this->storeCountryCode = '';
        $this->editingStoreId = null;
    }

    public function render(): View
    {
        return view('stores::admin.index', [
            'countryOptions' => CountryData::options(),
        ]);
    }
}
