<?php

declare(strict_types=1);

namespace App\Domain\Activities\Presentation\Livewire\Embedded;

use App\Domain\Activities\Presentation\Livewire\Concerns\HasActivityActions;
use App\Models\StockLevel;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class StockLevelActivities extends Component
{
    use HasActivityActions;

    public StockLevel $stockLevel;

    public function mount(StockLevel $stockLevel): void
    {
        $this->stockLevel = $stockLevel->load(['product', 'store']);
    }

    public function rendering(View $view): void
    {
        $name = $this->stockLevel->item_name ?? ('Asset #'.$this->stockLevel->asset_number);
        $view->title($name.' — Activities');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'columns' => $this->activityColumns(),
            'scopes' => [
                'forStockLevel' => $this->stockLevel->id,
            ],
        ];
    }

    public function render(): View
    {
        return view('activities::embedded.stock-levels', $this->with());
    }
}
