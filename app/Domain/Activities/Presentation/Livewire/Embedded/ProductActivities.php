<?php

declare(strict_types=1);

namespace App\Domain\Activities\Presentation\Livewire\Embedded;

use App\Domain\Activities\Presentation\Livewire\Concerns\HasActivityActions;
use App\Models\Product;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class ProductActivities extends Component
{
    use HasActivityActions;

    public Product $product;

    public function mount(Product $product): void
    {
        $this->product = $product->loadCount(['stockLevels', 'accessories', 'attachments']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name.' — Activities');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'columns' => $this->activityColumns(),
            'scopes' => [
                'forProduct' => $this->product->id,
            ],
        ];
    }

    public function render(): View
    {
        return view('activities::embedded.products', $this->with());
    }
}
