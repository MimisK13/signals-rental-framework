<?php

declare(strict_types=1);

namespace App\Domain\ReferenceData\Presentation\Livewire\Admin;

use App\Domain\ReferenceData\Models\Country;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')] #[Title('Countries')] final class Countries extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function toggleActive(int $countryId): void
    {
        Gate::authorize('settings.manage');

        $country = Country::findOrFail($countryId);
        $country->update(['is_active' => ! $country->is_active]);
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'countries' => Country::query()
                ->when($this->search, fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%")->orWhere('code', 'ilike', "%{$s}%"))
                ->orderBy('name')
                ->paginate(50),
        ];
    }

    public function render(): View
    {
        return view('reference-data::admin.countries', $this->with());
    }
}
