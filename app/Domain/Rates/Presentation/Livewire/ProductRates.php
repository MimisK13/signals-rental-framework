<?php

declare(strict_types=1);

namespace App\Domain\Rates\Presentation\Livewire;

use App\Domain\Rates\Actions\DeleteProductRate;
use App\Models\Product;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class ProductRates extends Component
{
    public Product $product;

    public function mount(Product $product): void
    {
        Gate::authorize('rates.view');

        $this->product = $product;
        $this->loadRates();
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name.' — Rates');
    }

    public function deleteRate(int $rateId): void
    {
        $rate = $this->product->rates()->findOrFail($rateId);

        (new DeleteProductRate)($rate);

        $this->loadRates();
        $this->dispatch('rate-deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(): array
    {
        return [
            'rates' => $this->product->rates()->with(['rateDefinition', 'store', 'priceCategory'])->orderByDesc('priority')->orderBy('id')->get(),
        ];
    }

    private function loadRates(): void
    {
        $this->product->load(['rates.rateDefinition', 'rates.store', 'rates.priceCategory'])->loadCount('rates');
    }

    public function render(): View
    {
        return view('rates::product-rates', $this->viewData());
    }
}
