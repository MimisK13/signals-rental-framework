<?php

declare(strict_types=1);

namespace App\Domain\Tax\Presentation\Livewire;

use App\Domain\Tax\Actions\DeleteTaxZone;
use App\Domain\Tax\Models\TaxZone;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Tax Zones')]
final class Zones extends Component
{
    /** @var Collection<int, TaxZone> */
    public $taxZones;

    public function mount(): void
    {
        $this->loadTaxZones();
    }

    public function loadTaxZones(): void
    {
        $this->taxZones = TaxZone::query()->withCount('members')->orderByDesc('priority')->orderBy('name')->get();
    }

    public function deleteTaxZone(int $taxZoneId): void
    {
        $taxZone = TaxZone::findOrFail($taxZoneId);
        (new DeleteTaxZone)($taxZone);
        $this->loadTaxZones();
    }

    public function render(): View
    {
        return view('tax::admin.zones');
    }
}
